(function() {
    var container;
    function getContainer() {
        if (!container) {
            container = document.createElement('div');
            container.className = 'wcb-toast-container';
            document.body.appendChild(container);
        }
        return container;
    }
    window.wcbToast = function(message, type) {
        type = type || 'info';
        var toast = document.createElement('div');
        toast.className = 'wcb-toast wcb-toast--' + type;
        toast.textContent = message;
        getContainer().appendChild(toast);
        setTimeout(function() {
            toast.classList.add('wcb-toast--exit');
            setTimeout(function() { toast.remove(); }, 200);
        }, 4000);
    };
})();
