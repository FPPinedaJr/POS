<?php
// Get the name of the current file (e.g., 'portal.php' or 'inventory.php')
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<header class="bg-white/70 backdrop-blur-xl border-b border-white sticky top-0 z-40 shadow-sm shadow-slate-200/50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="#" onclick="window.location.reload(); return false;" class="flex items-center focus:outline-none"
                aria-label="Refresh page">
                <img src="assets/images/logo.png" alt="Venda Track"
                    class="h-10 w-auto max-w-[200px] sm:max-w-[240px] object-contain">
            </a>
        </div>

        <div class="flex items-center gap-2 sm:gap-4">
            <div class="relative ml-2">
                <button id="profileTrigger"
                    class="flex items-center justify-center p-1 rounded-full hover:cursor-pointer hover:ring-4 hover:ring-indigo-500/10 transition-all focus:outline-none shadow-sm bg-white/50">
                    <img class="h-9 w-9 rounded-full object-cover border-2 border-white"
                        src="<?php echo htmlspecialchars($_SESSION['user_picture']); ?>" alt="User">
                </button>

                <div id="googleMenu"
                    class="hidden absolute right-0 mt-3 w-80 bg-white/95 backdrop-blur-2xl rounded-3xl shadow-2xl border border-white z-50 overflow-hidden">
                    <div class="p-6 flex flex-col items-center text-center relative">
                        <div class="absolute inset-0 linear-gradient-to-br from-indigo-500/5 to-purple-500/5"></div>

                        <div class="relative mb-3 z-10">
                            <img class="h-24 w-24 rounded-full object-cover border-4 border-white shadow-lg"
                                src="<?php echo htmlspecialchars($_SESSION['user_picture']); ?>" alt="User">
                        </div>

                        <h2 class="text-xl text-slate-900 font-bold z-10">Hi,
                            <?php echo htmlspecialchars($_SESSION['user_name']); ?>!
                        </h2>
                        <p class="text-sm font-medium text-slate-500 mb-4 z-10">
                            <?php echo htmlspecialchars($_SESSION['user_email']); ?>
                        </p>

                        <?php if (empty($_SESSION['google_id'])): ?>
                            <button id="openPassModal"
                                class="mt-4 px-6 py-2 border hover:cursor-pointer border-slate-200 bg-white/80 rounded-full text-sm font-semibold text-slate-700 hover:text-indigo-600 hover:bg-white hover:border-indigo-200 hover:shadow-md transition-all z-10">
                                Change password
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="bg-slate-50/80 p-2 border-t border-slate-100">

                        <?php if ($currentPage !== 'portal.php'): ?>
                            <a href="portal.php"
                                class="w-full flex items-center gap-3 px-4 py-3 text-sm font-bold text-slate-700 hover:bg-white hover:text-indigo-600 hover:shadow-sm rounded-2xl transition-all mb-1">
                                <i class="fa-solid fa-house text-indigo-400"></i>
                                <span>Home</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($currentPage === 'inventory.php'): ?>
                            <button id="openHistorySidebar"
                                class="w-full hover:cursor-pointer flex items-center gap-3 px-4 py-3 text-sm font-bold text-slate-700 hover:bg-white hover:text-indigo-600 hover:shadow-sm rounded-2xl transition-all">
                                <i class="fa-solid fa-clock-rotate-left text-indigo-400"></i>
                                <span>Inventory History</span>
                            </button>
                        <?php endif; ?>

                        <a href="includes/logout.php"
                            class="flex items-center gap-3 px-4 py-3 text-sm font-bold text-red-600 hover:bg-red-50 hover:shadow-sm rounded-2xl transition-all mt-1">
                            <i class="fa-solid fa-right-from-bracket text-red-400"></i>
                            <span>Sign out</span>
                        </a>
                    </div>

                    <div class="p-3 text-center border-t border-slate-100 bg-white">
                        <button type="button" id="open-legal-modal"
                            class="text-[10px] font-bold text-slate-400 uppercase tracking-widest hover:text-indigo-600 transition-colors cursor-pointer">
                            About Us
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>