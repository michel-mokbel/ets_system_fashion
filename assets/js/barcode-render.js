/**
 * Barcode preview and print helper for the admin barcode console.
 *
 * Responsibilities:
 * - Read dataset attributes from `#barcodeArea` to determine the symbology, format, and number of copies to render.
 * - Stream HTML/SVG barcode markup from `barcode_image.php` via the Fetch API and inject multiple print-ready panels.
 * - Provide a lightweight print dialog that waits for all images to load before invoking `window.print()` in a popup.
 *
 * Dependencies:
 * - Native browser APIs (Fetch, DOM) – no jQuery requirements.
 * - Server endpoint `barcode_image.php` which returns the generated barcode markup for the requested format.
 */

document.addEventListener('DOMContentLoaded', function() {
    const barcodeArea = document.getElementById('barcodeArea');
    if (!barcodeArea) return;
    const barcode = barcodeArea.getAttribute('data-barcode');
    const format = barcodeArea.getAttribute('data-format') || 'png';
    const quantity = parseInt(barcodeArea.getAttribute('data-quantity') || '1', 10);
    if (!barcode) {
        barcodeArea.innerHTML = '<div class="text-danger">No barcode value found.</div>';
        return;
    }
    // Render multiple barcodes
    let html = '';
    let loaded = 0;
    for (let i = 0; i < quantity; i++) {
        fetch(`barcode_image.php?barcode=${encodeURIComponent(barcode)}&format=${encodeURIComponent(format)}`)
            .then(res => res.text())
            .then(barcodeHtml => {
                html += `<div class='barcode-print-item mb-4' style='margin-bottom:32px !important; padding-bottom:8px; page-break-inside:avoid;'>${barcodeHtml}</div>`;
                loaded++;
                if (loaded === quantity) {
                    barcodeArea.innerHTML = `<div class='row'>${html}</div>`;
                }
            });
    }

    // Print button
    const printBtn = document.getElementById('printBarcodeBtn');
    if (printBtn) {
        printBtn.addEventListener('click', function() {
            const printContents = barcodeArea.innerHTML;
            const win = window.open('', '', 'width=400,height=300');
            win.document.write('<html><head><title>Print Barcode</title></head><body>' + printContents + '</body></html>');
            win.document.close();

            // Wait for all images to load before printing
            const images = win.document.images;
            if (images.length === 0) {
                win.print();
            } else {
                let loaded = 0;
                for (let i = 0; i < images.length; i++) {
                    images[i].onload = images[i].onerror = function() {
                        loaded++;
                        if (loaded === images.length) {
                            win.print();
                        }
                    };
                }
            }
        });
    }
}); 