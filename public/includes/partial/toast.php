<?php
// Check if a toast message is set in the session
if (isset($_SESSION['toast'])) {
    $toastType = $_SESSION['toast']['type'];
    $toastMsg = $_SESSION['toast']['message'];

    // Set colors based on success or error
    $bgColor = ($toastType === 'success') ? 'bg-teal-500' : 'bg-red-500';
    $icon = ($toastType === 'success') ? 'fa-check-circle' : 'fa-circle-exclamation';

    echo "
    <div id='toast-notification' class='fixed top-5 right-5 z-50 flex items-center w-full max-w-xs p-4 space-x-3 text-white $bgColor rounded-xl shadow-lg transition-opacity duration-500'>
        <i class='fa-solid $icon text-xl'></i>
        <div class='text-sm font-semibold'>$toastMsg</div>
        <button type='button' onclick='document.getElementById(\"toast-notification\").remove()' class='ml-auto -mx-1.5 -my-1.5 bg-transparent text-white hover:text-gray-200 rounded-lg focus:ring-2 focus:ring-white p-1.5 inline-flex h-8 w-8' aria-label='Close'>
            <i class='fa-solid fa-xmark'></i>
        </button>
    </div>
    <script>
        // Auto dismiss after 4 seconds
        setTimeout(() => {
            const toast = document.getElementById('toast-notification');
            if (toast) {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 500);
            }
        }, 4000);
    </script>
    ";

    // Clear the toast so it doesn't show up on refresh
    unset($_SESSION['toast']);
}
?>