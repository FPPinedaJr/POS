<footer class="border-t border-slate-200/60 bg-white/40 backdrop-blur-md relative z-10 mt-auto">
    <div
        class="max-w-7xl mx-auto px-6 py-5 flex items-center justify-between text-xs font-bold uppercase tracking-widest text-slate-400">
        <div>&copy; <?php echo date('Y'); ?> Inventory</div>
        <div class="hidden sm:block">Signed in as <span
                class="text-indigo-500"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span></div>
    </div>
</footer>