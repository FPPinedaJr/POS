<?php
require_once 'includes/gClienAuth.php';

if (isset($_SESSION['user_id'])) {
    header('Location: portal.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome | Inventory Tracker</title>
    <link rel="stylesheet" href="assets/css/output.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <style>
        @keyframes float {
            0% {
                transform: translate(0px, 0px) scale(1);
            }

            33% {
                transform: translate(30px, -50px) scale(1.1);
            }

            66% {
                transform: translate(-20px, 20px) scale(0.9);
            }

            100% {
                transform: translate(0px, 0px) scale(1);
            }
        }

        .animate-blob {
            animation: float 7s infinite alternate;
        }

        .animation-delay-2000 {
            animation-delay: 2s;
        }

        .animation-delay-4000 {
            animation-delay: 4s;
        }
    </style>
</head>

<body class="bg-slate-100 flex items-center justify-center min-h-screen overflow-hidden relative font-sans">

    <div class="absolute top-0 -left-10 w-120 h-120 bg-blue-400/75 rounded-full filter blur-2xl animate-blob"></div>
    <div
        class="absolute top-10 -right-10 w-100 h-100 bg-teal-300/75 rounded-full filter blur-2xl animate-blob animation-delay-2000">
    </div>
    <div
        class="absolute bottom-50 left-1/2 w-64 h-64 bg-fuchsia-400/80 rounded-full filter blur-3xl animate-blob animation-delay-5000">
    </div>
    <div
        class="absolute -bottom-10 left-1/3 w-96 h-96 bg-purple-400/60 rounded-full filter blur-2xl animate-blob animation-delay-4000">
    </div>

    <div id="login-wrapper"
        class="absolute w-full h-full flex justify-center items-center transition-all duration-500 ease-in-out transform translate-x-0 opacity-100 pointer-events-auto z-20">
        <?php include_once("./includes/partial/login.php") ?>
    </div>

    <div id="signup-wrapper"
        class="absolute w-full h-full flex justify-center items-center transition-all duration-500 ease-in-out transform translate-x-12 opacity-0 pointer-events-none z-10">
        <?php include_once("./includes/partial/registration.php") ?>
    </div>

    <script src="assets/js/jquery-4.0.0.min.js"></script>
    <script src="assets/js/toast-helper.js"></script>
    <script>
        $(document).ready(function () {

            // Intercept form submissions
            $('form').on('submit', function (e) {
                const actionUrl = $(this).attr('action');

                // Only intercept if the form is targeting our PHP processors
                if (actionUrl && (actionUrl.includes('./includes/login.php') || actionUrl.includes('./includes/register.php'))) {
                    e.preventDefault();

                    const form = $(this);
                    const submitBtn = form.find('button[type="submit"]');
                    const originalBtnText = submitBtn.html();

                    // Disable button and show loading state
                    submitBtn.html('<i class="fas fa-spinner fa-spin mr-2"></i> Processing...').prop('disabled', true);

                    $.ajax({
                        url: actionUrl,
                        type: 'POST',
                        data: form.serialize(),
                        dataType: 'json',
                        success: function (response) {
                            showToast(response.status, response.message);

                            if (response.status === 'success') {
                                // Wait 1.5 seconds for the user to read the toast, then redirect
                                setTimeout(() => {
                                    if (actionUrl.includes('./includes/login.php')) {
                                        window.location.href = 'portal.php';
                                    } else {
                                        // If registration is successful, redirect to login page (adjust path if needed)
                                        window.location.href = 'index.php';
                                    }
                                }, 1500);
                            } else {
                                // Re-enable button on error
                                submitBtn.html(originalBtnText).prop('disabled', false);
                            }
                        },
                        error: function () {
                            showToast('error', 'A server error occurred. Please try again.');
                            submitBtn.html(originalBtnText).prop('disabled', false);
                        }
                    });
                }
            });

            $(document).on('click', 'a[href="signup.php"]', function (e) {
                e.preventDefault();

                $('#login-wrapper')
                    .removeClass('translate-x-0 opacity-100 pointer-events-auto z-20')
                    .addClass('-translate-x-12 opacity-0 pointer-events-none z-10');

                $('#signup-wrapper')
                    .removeClass('translate-x-12 opacity-0 pointer-events-none z-10')
                    .addClass('translate-x-0 opacity-100 pointer-events-auto z-20');
            });

            $(document).on('click', 'a[href="includes/partial/login.php"]', function (e) {
                e.preventDefault();

                $('#signup-wrapper')
                    .removeClass('translate-x-0 opacity-100 pointer-events-auto z-20')
                    .addClass('translate-x-12 opacity-0 pointer-events-none z-10');

                $('#login-wrapper')
                    .removeClass('-translate-x-12 opacity-0 pointer-events-none z-10')
                    .addClass('translate-x-0 opacity-100 pointer-events-auto z-20');
            });

            $(document).on('click', '.toggle-password', function () {
                const input = $(this).siblings('input');
                const icon = $(this).find('i');

                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                } else {
                    input.attr('type', 'password');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                }
            });

        });
    </script>
</body>

</html>