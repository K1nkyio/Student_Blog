</main>
    </div><!-- /.admin-main -->
</div><!-- /.admin-app -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    'use strict';

    const body        = document.body;
    const toggleBtn   = document.getElementById('sidebarToggle');
    const collapseBtn = document.getElementById('sidebarCollapse');
    const backdrop    = document.getElementById('adminBackdrop');

    /* ── helpers ── */
    const isMobile    = () => window.innerWidth <= 992;
    const openMobile  = () => body.classList.add('admin-sidebar-open');
    const closeMobile = () => body.classList.remove('admin-sidebar-open');

    const setCollapsed = (collapsed) => {
        body.classList.toggle('admin-sidebar-collapsed', collapsed);
        try { localStorage.setItem('adminSidebarCollapsed', collapsed ? '1' : '0'); } catch (_) {}
        syncCollapseIcon();
    };

    const syncCollapseIcon = () => {
        const icon = document.getElementById('collapseIcon');
        if (!icon) return;
        const isCollapsed = body.classList.contains('admin-sidebar-collapsed');
        icon.innerHTML = isCollapsed
            ? '<path d="M9 18l6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/>'
            : '<path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/>';
        if (collapseBtn) collapseBtn.setAttribute('aria-label', isCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
    };

    /* ── events ── */
    if (toggleBtn)   toggleBtn.addEventListener('click', () => body.classList.toggle('admin-sidebar-open'));
    if (collapseBtn) collapseBtn.addEventListener('click', () => { if (!isMobile()) setCollapsed(!body.classList.contains('admin-sidebar-collapsed')); });
    if (backdrop)    backdrop.addEventListener('click', closeMobile);

    window.addEventListener('resize', () => { if (!isMobile()) closeMobile(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeMobile(); });

    /* ── nav link ripple ── */
    document.querySelectorAll('.sb-link').forEach(link => {
        link.addEventListener('click', function (e) {
            const r = document.createElement('span');
            r.style.cssText = `
                position:absolute; border-radius:50%;
                background:rgba(255,255,255,.14);
                transform:scale(0);
                animation:sbRipple .4s ease-out forwards;
                pointer-events:none;
                width:70px; height:70px;
                left:${e.offsetX - 35}px; top:${e.offsetY - 35}px;
            `;
            this.appendChild(r);
            setTimeout(() => r.remove(), 450);
        });
    });

    /* ── restore collapse state ── */
    try {
        if (!isMobile() && localStorage.getItem('adminSidebarCollapsed') === '1') {
            body.classList.add('admin-sidebar-collapsed');
        }
    } catch (_) {}

    syncCollapseIcon();

    /* ── inject ripple keyframe once ── */
    if (!document.getElementById('sb-ripple-style')) {
        const s = document.createElement('style');
        s.id = 'sb-ripple-style';
        s.textContent = '@keyframes sbRipple{to{transform:scale(2.5);opacity:0}}';
        document.head.appendChild(s);
    }

    /* ── auto-dismiss alerts ── */
    document.querySelectorAll('.alert:not(.alert-permanent)').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity .4s ease, transform .4s ease';
            el.style.opacity = '0';
            el.style.transform = 'translateY(-4px)';
            setTimeout(() => el.remove(), 420);
        }, 4500);
    });

})();
</script>
</body>
</html>
