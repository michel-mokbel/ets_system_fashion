<?php
/**
 * Barcode Generation Console
 * --------------------------
 * Allows inventory-privileged administrators to generate printable barcode
 * sheets for catalog items. The page enforces authentication, loads catalog
 * metadata for selection, and renders preview/print controls that embed
 * responses from `admin/barcode_image.php`. Client-side interactions are
 * handled in `assets/js/barcode-render.js`, which requests the chosen format
 * and duplicates of the barcode for rapid label printing.
 */
// admin/barcode.php
require_once __DIR__ . '/../includes/session_config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in and has access to inventory
if (!is_logged_in()) {
    redirect('../index.php');
}

// Only admins and inventory managers can access barcode generation
if (!can_access_inventory()) {
    redirect('../index.php');
}

$code = $_GET['code'] ?? '';
$format = $_GET['format'] ?? 'png';
$quantity = isset($_GET['quantity']) ? max(1, intval($_GET['quantity'])) : 1;

function get_items($conn) {
    $items = [];
    $result = $conn->query("SELECT item_code, name FROM inventory_items ORDER BY name ASC");
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    return $items;
}

require_once '../includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
<?php
if (!$code) {
    // Show selection form
    $items = get_items($conn);
    ?>
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="mb-4">Generate Barcodes</h2>
                <form method="get">
                    <div class="mb-3">
                        <label for="code" class="form-label">Select Item</label>
                        <select name="code" id="code" class="form-select" required>
                            <option value="">-- Select Item --</option>
                            <?php foreach ($items as $item): ?>
                                <option value="<?php echo htmlspecialchars($item['item_code']); ?>"><?php echo htmlspecialchars($item['name'] . ' (' . $item['item_code'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="quantity" class="form-label">Number of Barcodes</label>
                        <input type="number" name="quantity" id="quantity" class="form-control" value="1" min="1" max="100" required>
                    </div>
                    <div class="mb-3">
                        <label for="format" class="form-label">Format</label>
                        <select name="format" id="format" class="form-select">
                            <option value="png">PNG</option>
                            <option value="jpg">JPG</option>
                            <option value="svg">SVG</option>
                            <option value="html">HTML</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Generate</button>
                </form>
            </div>
        </div>
    <?php
} else {
    // Lookup item by item_code
    $stmt = $conn->prepare("SELECT id FROM inventory_items WHERE item_code = ? LIMIT 1");
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $item = $result->fetch_assoc();
        $item_id = $item['id'];
        // Get the barcode value from the barcodes table
        $stmt2 = $conn->prepare("SELECT barcode FROM barcodes WHERE item_id = ? LIMIT 1");
        $stmt2->bind_param('i', $item_id);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        if ($result2 && $result2->num_rows > 0) {
            $barcode_value = $result2->fetch_assoc()['barcode'];
        } else {
            echo '<div class="alert alert-danger mt-4">No barcode found for item: ' . htmlspecialchars($code) . '</div>';
            require_once '../includes/footer.php';
            exit;
        }
    } else {
        echo '<div class="alert alert-danger mt-4">Item not found for code: ' . htmlspecialchars($code) . '</div>';
        require_once '../includes/footer.php';
        exit;
    }
    ?>
        <div class="card mb-4">
            <div class="card-body text-center">
                <div class="barcode-label mb-3">Item Code: <strong><?php echo htmlspecialchars($code); ?></strong></div>
                <div id="barcodeArea" data-barcode="<?php echo htmlspecialchars($barcode_value); ?>" data-format="<?php echo htmlspecialchars($format); ?>" data-quantity="<?php echo $quantity; ?>">
                    <!-- Barcode will be rendered by JS -->
                </div>
                <div class="mt-3">
                    <form method="get" class="d-inline">
                        <input type="hidden" name="code" value="<?php echo htmlspecialchars($code); ?>">
                        <input type="hidden" name="quantity" value="<?php echo $quantity; ?>">
                        <select name="format" class="form-select d-inline w-auto">
                            <option value="png"<?php if($format==='png')echo' selected';?>>PNG</option>
                            <option value="jpg"<?php if($format==='jpg')echo' selected';?>>JPG</option>
                            <option value="svg"<?php if($format==='svg')echo' selected';?>>SVG</option>
                            <option value="html"<?php if($format==='html')echo' selected';?>>HTML</option>
                        </select>
                        <button type="submit" class="btn btn-outline-primary">Change Format</button>
                    </form>
                    <button class="btn btn-primary no-print" id="printBarcodeBtn"><i class="bi bi-printer"></i> Print</button>
                    <a href="barcode.php" class="btn btn-secondary no-print">Back</a>
                </div>
            </div>
        </div>
        <script src="../assets/js/barcode-render.js"></script>
    <?php
}
?>
        </div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?> 