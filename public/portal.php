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

$hour = date('H');
$greeting = "Welcome back";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="referrer" content="no-referrer">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMILE | Portal</title>
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
    <link rel="stylesheet" href="assets/css/output.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
</head>

<body class="bg-white min-h-screen flex flex-col text-slate-800 font-sans m-0 overflow-x-hidden"
    style="background-image: linear-gradient(to right, #f1f5f9 1px, transparent 1px), linear-gradient(to bottom, #f1f5f9 1px, transparent 1px); background-size: 20px 20px;">

    <?php include_once("includes/partial/header.php"); ?>

    <main
        class="flex-grow flex flex-col items-center justify-center max-w-7xl mx-auto px-4 sm:px-6 py-8 md:py-16 w-full">

        <div class="text-center mb-10 md:mb-16">
            <h1 class="text-3xl md:text-5xl font-extrabold text-slate-950 mb-3 tracking-tighter">
                <?php echo $greeting; ?>, <?php echo $firstName; ?>!
            </h1>
            <p class="text-base md:text-xl text-slate-600 max-w-lg mx-auto">
                What would you like to work on today?
            </p>
        </div>

        <div class="grid gap-4 w-full max-w-sm mx-auto md:hidden">
            <a href="pos.php"
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

        <div class="hidden md:grid grid-cols-1 md:grid-cols-3 gap-8 md:gap-10 w-full">

            <div
                class="bg-white p-8 rounded-3xl shadow-lg border border-slate-100 flex flex-col items-center text-center transition hover:shadow-2xl hover:-translate-y-1">
                <div class="p-4 bg-teal-100 text-teal-600 rounded-full mb-6">
                    <i class="fa-solid fa-cart-shopping text-4xl"></i>
                </div>
                <h2 class="text-2xl font-bold text-slate-950 mb-3">Start selling</h2>
                <p class="text-slate-600 mb-8 flex-grow">Set up a new sale transaction and manage customer purchases.
                </p>
                <a href="pos.php"
                    class="w-full text-center bg-teal-600 text-white font-semibold py-4 px-6 rounded-xl transition duration-200 ease-in-out hover:bg-teal-700 shadow-md">
                    Launch Register
                </a>
            </div>

            <div
                class="bg-white p-8 rounded-3xl shadow-lg border border-slate-100 flex flex-col items-center text-center transition hover:shadow-2xl hover:-translate-y-1">
                <div class="p-4 bg-indigo-100 text-indigo-600 rounded-full mb-6">
                    <i class="fa-solid fa-boxes-stacked text-4xl"></i>
                </div>
                <h2 class="text-2xl font-bold text-slate-950 mb-3">Manage inventory</h2>
                <p class="text-slate-600 mb-8 flex-grow">Add new products, update stock counts, and edit details.</p>
                <a href="inventory.php"
                    class="w-full text-center bg-indigo-600 text-white font-semibold py-4 px-6 rounded-xl transition duration-200 ease-in-out hover:bg-indigo-700 shadow-md">
                    View Inventory
                </a>
            </div>

            <div
                class="bg-white p-8 rounded-3xl shadow-lg border border-slate-100 flex flex-col items-center text-center transition hover:shadow-2xl hover:-translate-y-1">
                <div class="p-4 bg-fuchsia-100 text-fuchsia-600 rounded-full mb-6">
                    <i class="fa-solid fa-chart-line text-4xl"></i>
                </div>
                <h2 class="text-2xl font-bold text-slate-950 mb-3">View Reports</h2>
                <p class="text-slate-600 mb-8 flex-grow">Analyze sales performance, generate summaries, and track
                    metrics.</p>
                <a href="reports.php"
                    class="w-full text-center bg-fuchsia-600 text-white font-semibold py-4 px-6 rounded-xl transition duration-200 ease-in-out hover:bg-fuchsia-700 shadow-md">
                    Open Analytics
                </a>
            </div>

        </div>

    </main>

    <?php include_once("includes/partial/footer.php"); ?>

</body>

</html>