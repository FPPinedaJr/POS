<div
    class="z-10 w-11/12 max-w-md max-h-[90vh] overflow-y-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none] rounded-3xl border border-white/60 bg-white/40 backdrop-blur-xl shadow-[0_8px_32px_0_rgba(31,38,135,0.1)] flex flex-col items-center p-6 md:p-8">

    <div
        class="bg-indigo-500 w-14 h-14 shrink-0 rounded-2xl mb-4 flex items-center justify-center shadow-lg shadow-indigo-200/50">
        <i class="fas fa-boxes-stacked text-white text-2xl"></i>
    </div>

    <h2 class="text-2xl shrink-0 font-extrabold text-slate-800 mb-1">Welcome Back</h2>
    <p class="text-slate-600 shrink-0 mb-5 text-center text-sm font-medium">Log in to your inventory dashboard.</p>

    <form action="./includes/login.php" method="POST" class="w-full flex flex-col shrink-0">

        <div class="mb-3 relative">
            <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none">
                <i class="fas fa-envelope text-slate-400"></i>
            </div>
            <input type="email" name="email" placeholder="Email Address" required
                class="w-full pl-11 pr-4 py-3 rounded-xl bg-white/50 border border-white/60 text-slate-700 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:bg-white transition-all shadow-sm">
        </div>

        <div class="mb-4 relative">
            <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none">
                <i class="fas fa-lock text-slate-400"></i>
            </div>
            <input type="password" name="password" placeholder="Password" required
                class="w-full pl-11 pr-11 py-3 rounded-xl bg-white/50 border border-white/60 text-slate-700 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:bg-white transition-all shadow-sm">
            <button type="button"
                class="absolute inset-y-0 right-0 flex items-center pr-4 text-slate-400 hover:text-blue-600 focus:outline-none transition-colors toggle-password">
                <i class="fas fa-eye-slash"></i>
            </button>
        </div>

        <button type="submit"
            class="w-full bg-indigo-500 hover:bg-indigo-600 text-white font-bold py-3 px-6 rounded-xl transition-colors duration-300 shadow-md hover:shadow-lg focus:ring-4 focus:ring-indigo-200 focus:outline-none cursor-pointer">
            Log In
        </button>

    </form>

    <div class="flex items-center w-full my-5 shrink-0">
        <div class="grow border-t border-slate-300/60"></div>
        <span class="px-4 text-xs text-slate-500 font-bold uppercase tracking-wider">or</span>
        <div class="grow border-t border-slate-300/60"></div>
    </div>

    <a href="<?php echo htmlspecialchars($loginUrl ?? '#'); ?>"
        class="group shrink-0 w-full flex items-center justify-center gap-3 bg-white hover:bg-slate-50 text-slate-700 font-bold py-3 px-6 rounded-xl transition-all duration-300 shadow-sm hover:shadow-md border border-white/80 focus:ring-4 focus:ring-blue-100 focus:outline-none">
        <i
            class="fa-brands fa-google text-blue-500 text-lg group-hover:scale-110 transition-transform duration-300"></i>
        Continue with Google
    </a>

    <p class="mt-5 shrink-0 text-sm text-slate-600 font-medium">
        Don't have an account? <a href="signup.php"
            class="text-blue-600 hover:text-blue-800 font-bold transition-colors">Sign up</a>
    </p>

</div>