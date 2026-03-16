;(function (window, $) {
    if (!$) return;

    function getToastContainer() {
        let $container = $('#toast-container');
        if (!$container.length) {
            $container = $('<div id="toast-container" class="fixed top-6 right-6 z-80 space-y-3 pointer-events-none"></div>');
            $('body').append($container);
        }
        return $container;
    }

    /**
     * Global reusable toast helper.
     * type: 'success' | 'error' | anything else (treated as 'error')
     * message: string
     */
    window.showToast = function (type, message) {
        const $toastContainer = getToastContainer();

        const normalized = (type || '').toString().toLowerCase();
        const isError = normalized === 'error' || normalized === 'danger' || normalized === 'fail';
        const isSuccess = normalized === 'success' || normalized === 'ok';

        const base = 'pointer-events-auto w-80 max-w-[90vw] rounded-2xl border shadow-xl px-5 py-4 backdrop-blur-md';
        const colors = isError
            ? 'bg-red-50/90 border-red-200 text-red-800'
            : 'bg-emerald-50/90 border-emerald-200 text-emerald-800';
        const icon = isError ? 'fa-circle-exclamation' : 'fa-circle-check';

        const safeMessage = (message || '').toString();

        const $toast = $('<div></div>').addClass(base + ' ' + colors).hide();
        const $wrap = $(
            '<div class="flex items-center">' +
            '  <div class="flex items-start gap-3">' +
            '    <i class="fa-solid ' + icon + ' mt-0.5 text-lg"></i>' +
            '    <div class="text-sm font-bold leading-snug"></div>' +
            '  </div>' +
            '</div>'
        );
        $wrap.find('div.text-sm').text(safeMessage);

        const $close = $('<button type="button" class="ml-auto text-inherit/50 hover:text-inherit hover:bg-black/5 p-1 rounded-md transition-all"><i class="fa-solid fa-xmark"></i></button>');

        $close.on('click', function () {
            $toast.stop(true, true).fadeOut(150, function () { $(this).remove(); });
        });

        $toast.append($wrap.append($close));
        $toastContainer.append($toast);
        $toast.fadeIn(150);

        setTimeout(function () {
            $toast.fadeOut(250, function () { $(this).remove(); });
        }, 3000);
    };
})(window, window.jQuery);

