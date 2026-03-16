<?php
session_start();

// Protect the page: If not logged in, send them back to the login page
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Grab user details from the session
$name = htmlspecialchars($_SESSION['user_name']);
$firstName = explode(' ', trim($name))[0]; // Get just the first name for a friendlier greeting
$picture = htmlspecialchars($_SESSION['user_picture']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="referrer" content="no-referrer">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Main Portal</title>
    <link rel="stylesheet" href="assets/css/output.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
</head>

<body class="bg-gray-100 min-h-screen flex justify-center items-center text-gray-800 font-sans m-0">

    <div class="bg-white p-8 md:p-10 rounded-2xl shadow-xl text-center w-11/12 max-w-md">

        <img src="assets/images/logo.png" alt="Company Logo" class="mx-auto mb-6 max-w-[150px] h-auto"
            onerror="this.style.display='none'">

        <img src="<?php echo $picture; ?>" alt="User Avatar"
            class="w-20 h-20 rounded-full mx-auto mb-3 border-4 border-indigo-600 shadow-sm"
            referrerpolicy="no-referrer">
        <h1 class="text-2xl font-bold text-gray-900 mb-1">Hello,
            <?php echo $firstName; ?>! 👋
        </h1>
        <p class="text-gray-500 mb-8">What would you like to do today?</p>

        <div class="grid gap-4">
            <a href="dashboard.php"
                class="flex items-center justify-center bg-teal-600 text-white font-semibold text-lg py-4 px-6 rounded-xl transition duration-200 ease-in-out hover:bg-teal-700 hover:-translate-y-1 shadow-md">
                <i class="fa-solid fa-cart-shopping text-xl mr-3"></i> Start selling
            </a>

            <a href="inventory.php"
                class="flex items-center justify-center bg-indigo-600 text-white font-semibold text-lg py-4 px-6 rounded-xl transition duration-200 ease-in-out hover:bg-indigo-700 hover:-translate-y-1 shadow-md">
                <i class="fa-solid fa-boxes-stacked text-xl mr-3"></i> Manage inventory
            </a>

            <a href="reports.php"
                class="flex items-center justify-center bg-fuchsia-600 text-white font-semibold text-lg py-4 px-6 rounded-xl transition duration-200 ease-in-out hover:bg-fuchsia-700 hover:-translate-y-1 shadow-md">
                <i class="fa-solid fa-chart-line text-xl mr-3"></i> View Reports
            </a>
        </div>

        <a href="includes/logout.php"
            class="inline-block mt-8 text-sm text-gray-500 transition hover:text-red-600 hover:underline">
            <i class="fa-solid fa-right-from-bracket mr-1"></i> Sign out
        </a>

    </div>

</body>

</html>