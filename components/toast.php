<?php
// This is the fully fixed, self-contained toast component.
// It has no external file dependencies and can be safely included on any page.
?>
<div class="toast-container position-fixed top-0 end-0 p-3">
<?php
// Check if a toast message exists in the session.
if (isset($_SESSION['toast_message'])) {
    // Copy the message to a local variable, then immediately unset the session copy
    // to prevent the toast from showing again on a page refresh.
    $toast = $_SESSION['toast_message'];
    unset($_SESSION['toast_message']);

    // Set default values for safety.
    $toast_type = $toast['type'] ?? 'info';
    $toast_message = $toast['message'] ?? 'An unknown event occurred.';
    $toast_icon = 'info-circle-fill';

    // Determine the correct icon and Bootstrap class based on the type.
    switch ($toast_type) {
        case 'success':
            $toast_icon = 'check-circle-fill';
            break;
        case 'danger': // Using 'danger' matches the Bootstrap class 'text-bg-danger'
            $toast_icon = 'x-circle-fill';
            break;
        case 'warning':
            $toast_icon = 'exclamation-triangle-fill';
            break;
    }
?>
    <div id="liveToast" class="toast align-items-center text-bg-<?= htmlspecialchars($toast_type) ?> border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body fs-6">
                <i class="bi bi-<?= htmlspecialchars($toast_icon) ?> me-2"></i>
                <?= htmlspecialchars($toast_message) ?>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
<?php
}
?>
</div>