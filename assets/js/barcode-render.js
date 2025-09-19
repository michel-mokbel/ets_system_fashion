// assets/js/barcode-render.js

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