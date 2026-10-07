/**
 * Navbar mobile menu — scoped to .navbar only; touches nothing else.
 */
(function () {
    'use strict';

    var nav = document.querySelector('.navbar');
    if (!nav) {
        return;
    }

    var toggle = nav.querySelector('.navbar-toggle');
    var actions = nav.querySelector('#navbar-actions');
    if (!toggle || !actions) {
        return;
    }

    // Marks JS availability: the mobile collapse rules only apply under
    // .nav-js so a missing script can never hide the controls.
    nav.classList.add('nav-js');

    var icon = toggle.querySelector('i');

    var setOpen = function (open) {
        actions.classList.toggle('open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (icon) {
            icon.classList.toggle('fa-bars', !open);
            icon.classList.toggle('fa-xmark', open);
        }
    };

    toggle.addEventListener('click', function () {
        setOpen(!actions.classList.contains('open'));
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });

    document.addEventListener('click', function (event) {
        if (!nav.contains(event.target)) {
            setOpen(false);
        }
    });
})();
