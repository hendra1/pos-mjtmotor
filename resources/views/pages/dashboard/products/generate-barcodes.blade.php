@extends( 'layout.dashboard' )

@section( 'layout.dashboard.body' )
<div class="flex-auto flex flex-col" id="generate-barcodes-app">
    <div class="p-4">
        <div class="mb-4">
            <h3 class="text-2xl font-bold text-primary">{{ __( 'Generate Barcodes' ) }}</h3>
            <p class="text-secondary text-sm">{{ __( 'Generate and print product barcodes optimized for 80mm thermal printer.' ) }}</p>
        </div>

        {{-- Search & Controls --}}
        <div class="ns-box shadow rounded mb-4">
            <div class="ns-box-header p-3 border-b flex items-center justify-between">
                <h4 class="font-semibold">{{ __( 'Search Products' ) }}</h4>
            </div>
            <div class="ns-box-body p-3">
                <div class="flex flex-col md:flex-row gap-3">
                    <div class="flex-1 relative">
                        <input
                            type="text"
                            id="search-product"
                            placeholder="{{ __( 'Type product name, barcode or SKU...' ) }}"
                            class="ns-input w-full px-3 py-2 border rounded"
                            autocomplete="off"
                        />
                        <div id="search-results" class="absolute z-50 w-full bg-white shadow-lg border rounded mt-1 hidden max-h-64 overflow-y-auto"></div>
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-sm font-medium whitespace-nowrap">{{ __( 'Copies:' ) }}</label>
                        <input type="number" id="default-copies" value="1" min="1" max="100"
                            class="ns-input w-20 px-2 py-2 border rounded text-center" />
                    </div>
                </div>
            </div>
        </div>

        {{-- Product Queue --}}
        <div class="ns-box shadow rounded mb-4">
            <div class="ns-box-header p-3 border-b flex items-center justify-between">
                <h4 class="font-semibold">
                    {{ __( 'Barcode Queue' ) }}
                    <span id="queue-count" class="ml-2 text-xs bg-blue-500 text-white rounded-full px-2 py-0.5">0</span>
                </h4>
                <div class="flex gap-2">
                    <button id="clear-queue"
                        class="px-3 py-1 text-sm border rounded hover:bg-red-50 text-red-500 border-red-300 transition-colors">
                        {{ __( 'Clear All' ) }}
                    </button>
                    <button id="print-barcodes"
                        class="px-4 py-1 text-sm bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors flex items-center gap-1">
                        <i class="la la-print"></i>
                        {{ __( 'Print' ) }}
                    </button>
                </div>
            </div>
            <div class="ns-box-body p-3">
                <div id="empty-queue" class="text-center py-8 text-gray-400">
                    <i class="la la-barcode text-5xl block mb-2"></i>
                    <p>{{ __( 'No products added. Search and select products above.' ) }}</p>
                </div>
                <div id="product-queue" class="hidden">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left py-2 px-2">{{ __( 'Product' ) }}</th>
                                <th class="text-left py-2 px-2">{{ __( 'Barcode' ) }}</th>
                                <th class="text-left py-2 px-2">{{ __( 'Type' ) }}</th>
                                <th class="text-center py-2 px-2">{{ __( 'Copies' ) }}</th>
                                <th class="text-center py-2 px-2">{{ __( 'Preview' ) }}</th>
                                <th class="text-center py-2 px-2">{{ __( 'Action' ) }}</th>
                            </tr>
                        </thead>
                        <tbody id="queue-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Hidden Print Area --}}
<div id="print-area" class="hidden"></div>

@endsection

@section( 'layout.dashboard.footer' )
    @parent
    <style>
        /* Screen styles for barcode preview */
        .barcode-preview svg {
            max-width: 100%;
            height: auto;
        }

        /* 80mm Thermal Print Styles */
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            body > *:not(#print-area) {
                display: none !important;
            }

            #print-area {
                display: block !important;
            }

            .barcode-label {
                width: 72mm;
                min-height: 30mm;
                padding: 2mm 2mm;
                margin: 0;
                page-break-inside: avoid;
                display: flex;
                flex-direction: column;
                align-items: center;
                font-family: Arial, sans-serif;
            }

            .barcode-label .product-name {
                font-size: 8pt;
                font-weight: bold;
                text-align: center;
                width: 100%;
                margin-bottom: 1mm;
                word-break: break-word;
                line-height: 1.2;
            }

            .barcode-label .barcode-img {
                width: 100%;
                text-align: center;
            }

            .barcode-label .barcode-img svg {
                width: 65mm !important;
                height: auto !important;
            }

            .barcode-label .barcode-value {
                font-size: 7pt;
                text-align: center;
                margin-top: 0.5mm;
                letter-spacing: 1px;
            }

            .barcode-label .product-price {
                font-size: 9pt;
                font-weight: bold;
                text-align: center;
                margin-top: 0.5mm;
            }

            .print-separator {
                border: none;
                border-top: 1px dashed #ccc;
                margin: 1mm 0;
                width: 100%;
            }

            @page {
                size: 80mm auto;
                margin: 3mm 4mm;
            }
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>

    <script>
    (function() {
        'use strict';

        const searchInput   = document.getElementById('search-product');
        const searchResults = document.getElementById('search-results');
        const defaultCopies = document.getElementById('default-copies');
        const queueTbody    = document.getElementById('queue-tbody');
        const emptyQueue    = document.getElementById('empty-queue');
        const productQueue  = document.getElementById('product-queue');
        const queueCount    = document.getElementById('queue-count');
        const clearBtn      = document.getElementById('clear-queue');
        const printBtn      = document.getElementById('print-barcodes');
        const printArea     = document.getElementById('print-area');

        let queue       = [];
        let searchTimer = null;

        /**
         * Get the CSRF token from the meta tag
         */
        function getCsrfToken() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        }

        /**
         * Search products via NexoPOS API (POST /api/products/search)
         */
        function searchProducts( term ) {
            const url = `{{ ns()->url('/api/products/search') }}`;

            return fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ search: term })
            })
            .then(res => res.json())
            .catch(() => []);
        }

        /**
         * Render search results dropdown
         */
        function renderResults( products ) {
            if (!products || products.length === 0) {
                searchResults.innerHTML = '<div class="p-3 text-gray-500 text-sm">{{ __("No products found.") }}</div>';
                searchResults.classList.remove('hidden');
                return;
            }

            searchResults.innerHTML = products.map(p => {
                const barcode = p.barcode || p.sku || '';
                // Get sale_price from first unit_quantity if available
                const price = p.unit_quantities && p.unit_quantities.length > 0
                    ? (p.unit_quantities[0].sale_price || 0)
                    : 0;
                return `<div class="p-2 hover:bg-blue-50 cursor-pointer border-b last:border-0 flex justify-between items-center"
                    data-id="${p.id}"
                    data-name="${escapeHtml(p.name)}"
                    data-barcode="${escapeHtml(barcode)}"
                    data-barcode-type="${escapeHtml(p.barcode_type || 'code128')}"
                    data-price="${price}"
                    >
                    <div>
                        <div class="font-medium text-sm">${escapeHtml(p.name)}</div>
                        <div class="text-xs text-gray-500">
                            ${barcode ? '{{ __("Barcode") }}: ' + escapeHtml(barcode) : ''}
                            ${p.sku ? ' | SKU: ' + escapeHtml(p.sku) : ''}
                        </div>
                    </div>
                    <div class="text-xs text-blue-500 ml-2 font-medium">
                        ${price ? formatCurrency(price) : ''}
                    </div>
                </div>`;
            }).join('');

            searchResults.classList.remove('hidden');
        }

        /**
         * Format currency using NexoPOS settings
         */
        function formatCurrency( amount ) {
            const symbol = '{{ ns()->option->get("ns_currency_symbol", "Rp") }}';
            const position = '{{ ns()->option->get("ns_currency_position", "before") }}';
            const formatted = parseFloat(amount).toLocaleString('id-ID');
            return position === 'before' ? symbol + formatted : formatted + symbol;
        }

        /**
         * Add a product to the print queue
         */
        function addToQueue( product ) {
            const copies = parseInt(defaultCopies.value) || 1;

            // Check if product already in queue
            const existing = queue.find(q => q.id == product.id);
            if (existing) {
                existing.copies += copies;
                renderQueue();
                return;
            }

            queue.push({
                id: product.id,
                name: product.name,
                barcode: product.barcode,
                barcodeType: product.barcodeType,
                price: product.price,
                copies: copies,
            });

            renderQueue();
        }

        /**
         * Get barcode type string for JsBarcode
         */
        function getBarcodeFormat( type ) {
            const map = {
                'ean13':   'EAN13',
                'ean8':    'EAN8',
                'code128': 'CODE128',
                'code39':  'CODE39',
                'codabar': 'codabar',
                'upca':    'UPC',
                'upce':    'UPCE',
            };
            return map[type] || 'CODE128';
        }

        /**
         * Generate SVG barcode using JsBarcode
         */
        function generateBarcode( barcode, type ) {
            if (!barcode) {
                return '<div class="text-xs text-red-400 italic">No barcode set</div>';
            }

            const svgId = 'bc-' + Math.random().toString(36).substr(2, 9);
            const svgEl = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svgEl.id = svgId;

            try {
                JsBarcode(svgEl, barcode, {
                    format: getBarcodeFormat(type),
                    width: 1.5,
                    height: 40,
                    displayValue: true,
                    fontSize: 10,
                    margin: 2,
                    textMargin: 2,
                });
                return svgEl.outerHTML;
            } catch (e) {
                // If barcode type fails, try CODE128
                try {
                    JsBarcode(svgEl, barcode, {
                        format: 'CODE128',
                        width: 1.5,
                        height: 40,
                        displayValue: true,
                        fontSize: 10,
                        margin: 2,
                        textMargin: 2,
                    });
                    return svgEl.outerHTML;
                } catch(e2) {
                    return '<div class="text-xs text-red-400 italic">Invalid barcode</div>';
                }
            }
        }

        /**
         * Render the product queue table
         */
        function renderQueue() {
            queueCount.textContent = queue.length;

            if (queue.length === 0) {
                emptyQueue.classList.remove('hidden');
                productQueue.classList.add('hidden');
                return;
            }

            emptyQueue.classList.add('hidden');
            productQueue.classList.remove('hidden');

            queueTbody.innerHTML = queue.map((item, index) => {
                const barcodeSvg = generateBarcode(item.barcode, item.barcodeType);
                return `<tr class="border-b hover:bg-gray-50" data-index="${index}">
                    <td class="py-2 px-2">
                        <div class="font-medium">${escapeHtml(item.name)}</div>
                        <div class="text-xs text-gray-500">${escapeHtml(item.price ? formatCurrency(item.price) : '')}</div>
                    </td>
                    <td class="py-2 px-2">
                        <span class="text-xs font-mono">${escapeHtml(item.barcode || '—')}</span>
                    </td>
                    <td class="py-2 px-2">
                        <span class="text-xs uppercase">${escapeHtml(item.barcodeType || 'code128')}</span>
                    </td>
                    <td class="py-2 px-2 text-center">
                        <input type="number" value="${item.copies}" min="1" max="100"
                            class="ns-input w-16 px-2 py-1 border rounded text-center text-sm"
                            onchange="window.updateCopies(${index}, this.value)"
                        />
                    </td>
                    <td class="py-2 px-2 text-center">
                        <div class="barcode-preview flex justify-center" style="max-width:120px;margin:auto">
                            ${barcodeSvg}
                        </div>
                    </td>
                    <td class="py-2 px-2 text-center">
                        <button onclick="window.removeFromQueue(${index})"
                            class="text-red-400 hover:text-red-600 transition-colors">
                            <i class="la la-trash text-lg"></i>
                        </button>
                    </td>
                </tr>`;
            }).join('');
        }

        /**
         * Update copies for a queue item
         */
        window.updateCopies = function(index, value) {
            const copies = parseInt(value) || 1;
            if (queue[index]) {
                queue[index].copies = Math.max(1, copies);
            }
        };

        /**
         * Remove item from queue
         */
        window.removeFromQueue = function(index) {
            queue.splice(index, 1);
            renderQueue();
        };

        /**
         * Generate barcode SVG for print (larger dimensions)
         */
        function generateBarcodePrint( barcode, type ) {
            if (!barcode) return '<div style="text-align:center;font-size:8pt;color:red;">No barcode</div>';

            const svgEl = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            try {
                JsBarcode(svgEl, barcode, {
                    format: getBarcodeFormat(type),
                    width: 2,
                    height: 50,
                    displayValue: false,
                    margin: 2,
                });
                return svgEl.outerHTML;
            } catch(e) {
                try {
                    JsBarcode(svgEl, barcode, {
                        format: 'CODE128',
                        width: 2,
                        height: 50,
                        displayValue: false,
                        margin: 2,
                    });
                    return svgEl.outerHTML;
                } catch(e2) {
                    return '<div style="text-align:center;font-size:8pt;color:red;">Invalid barcode</div>';
                }
            }
        }

        /**
         * Build the print HTML content
         */
        function buildPrintContent() {
            let html = '';

            queue.forEach(item => {
                for (let i = 0; i < item.copies; i++) {
                    const barcodeSvg = generateBarcodePrint(item.barcode, item.barcodeType);
                    const price      = item.price ? formatCurrency(item.price) : '';

                    html += `<div class="barcode-label">
                        <div class="product-name">${escapeHtml(item.name)}</div>
                        ${price ? `<div class="product-price">${escapeHtml(price)}</div>` : ''}
                        <div class="barcode-img">${barcodeSvg}</div>
                        <div class="barcode-value">${escapeHtml(item.barcode || '')}</div>
                    </div>`;

                    // Add separator between labels (not after last one)
                    html += `<hr class="print-separator" />`;
                }
            });

            return html;
        }

        /**
         * Escape HTML entities
         */
        function escapeHtml( str ) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        // ── Event Listeners ──────────────────────────────────────────────────────

        // Debounced search
        searchInput.addEventListener('input', function() {
            const term = this.value.trim();
            clearTimeout(searchTimer);

            if (term.length < 2) {
                searchResults.classList.add('hidden');
                return;
            }

            searchTimer = setTimeout(() => {
                searchProducts(term).then(data => {
                    // API returns a Laravel collection directly as an array
                    const products = Array.isArray(data) ? data : [];
                    renderResults(products);
                });
            }, 350);
        });

        // Hide results on outside click
        document.addEventListener('click', function(e) {
            if (!searchResults.contains(e.target) && e.target !== searchInput) {
                searchResults.classList.add('hidden');
            }
        });

        // Select product from results
        searchResults.addEventListener('click', function(e) {
            const item = e.target.closest('[data-id]');
            if (!item) return;

            addToQueue({
                id:          item.dataset.id,
                name:        item.dataset.name,
                barcode:     item.dataset.barcode,
                barcodeType: item.dataset.barcodeType,
                price:       item.dataset.price,
            });

            searchInput.value = '';
            searchResults.classList.add('hidden');
        });

        // Clear all
        clearBtn.addEventListener('click', function() {
            if (queue.length === 0) return;
            if (confirm('{{ __("Are you sure you want to clear all items from the queue?") }}')) {
                queue = [];
                renderQueue();
            }
        });

        // Print
        printBtn.addEventListener('click', function() {
            if (queue.length === 0) {
                alert('{{ __("Please add at least one product to the queue.") }}');
                return;
            }

            printArea.innerHTML = buildPrintContent();
            printArea.classList.remove('hidden');
            window.print();
            printArea.classList.add('hidden');
        });

    })();
    </script>
@endsection
