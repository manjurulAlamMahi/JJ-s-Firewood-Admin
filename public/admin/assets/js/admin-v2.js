/* =============================================================================
   JJ's Firewood — Admin V2 shell behaviour

   Vanilla JS for the shell (drawer, nav, theme, tab bar) plus a thin jQuery
   layer for the DataTables integration, since every list page in this app is
   built on DataTables.

   Loaded after jQuery and before any page-level @push('scripts'), so the
   DataTables defaults set here apply to tables the pages create afterwards.
   ========================================================================== */
(function () {
    'use strict';

    var MOBILE_BP = 992;   // drawer / tab bar breakpoint
    var CARDS_BP = 768;    // table-to-card breakpoint

    var isMobile = function () { return window.innerWidth < MOBILE_BP; };
    var isNarrow = function () { return window.innerWidth < CARDS_BP; };

    /* -------------------------------------------------------------------------
       Theme
       The initial value is applied by an inline script in <head> to avoid a
       flash; this only wires up the toggle and keeps the icon in sync.
       ---------------------------------------------------------------------- */
    var Theme = {
        key: 'av-theme',

        current: function () {
            return document.documentElement.getAttribute('data-theme') || 'light';
        },

        apply: function (mode) {
            document.documentElement.setAttribute('data-theme', mode);
            try { localStorage.setItem(Theme.key, mode); } catch (e) { /* private mode */ }
            Theme.sync();
        },

        toggle: function () {
            Theme.apply(Theme.current() === 'dark' ? 'light' : 'dark');
        },

        sync: function () {
            var dark = Theme.current() === 'dark';
            document.querySelectorAll('[data-theme-icon]').forEach(function (el) {
                el.hidden = (el.getAttribute('data-theme-icon') === 'dark') !== dark;
            });
            document.querySelectorAll('[data-theme-label]').forEach(function (el) {
                el.textContent = dark ? 'Light mode' : 'Dark mode';
            });
        },

        init: function () {
            document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    Theme.toggle();
                });
            });
            Theme.sync();
        }
    };

    /* -------------------------------------------------------------------------
       Drawer (mobile sidebar)
       ---------------------------------------------------------------------- */
    var Drawer = {
        el: null,
        backdrop: null,
        lastFocus: null,

        isOpen: function () {
            return document.body.classList.contains('av-drawer-open');
        },

        open: function () {
            if (!Drawer.el || Drawer.isOpen()) return;
            Drawer.lastFocus = document.activeElement;
            document.body.classList.add('av-drawer-open', 'av-lock');
            Drawer.el.setAttribute('aria-hidden', 'false');
            document.querySelectorAll('[data-drawer-open]').forEach(function (b) {
                b.setAttribute('aria-expanded', 'true');
            });
            // Give focus to the drawer so Escape and Tab behave predictably.
            var target = Drawer.el.querySelector('[data-drawer-close], a, button');
            if (target) target.focus({ preventScroll: true });
        },

        close: function () {
            if (!Drawer.el || !Drawer.isOpen()) return;
            document.body.classList.remove('av-drawer-open', 'av-lock');
            Drawer.el.setAttribute('aria-hidden', isMobile() ? 'true' : 'false');
            document.querySelectorAll('[data-drawer-open]').forEach(function (b) {
                b.setAttribute('aria-expanded', 'false');
            });
            if (Drawer.lastFocus && Drawer.lastFocus.focus) {
                Drawer.lastFocus.focus({ preventScroll: true });
            }
        },

        toggle: function () {
            Drawer.isOpen() ? Drawer.close() : Drawer.open();
        },

        trapFocus: function (e) {
            if (e.key !== 'Tab' || !Drawer.isOpen() || !isMobile()) return;
            var focusables = Drawer.el.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])'
            );
            if (!focusables.length) return;
            var first = focusables[0];
            var last = focusables[focusables.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        },

        initSwipe: function () {
            var startX = null;
            var startY = null;

            Drawer.el.addEventListener('touchstart', function (e) {
                if (!Drawer.isOpen()) return;
                startX = e.touches[0].clientX;
                startY = e.touches[0].clientY;
            }, { passive: true });

            Drawer.el.addEventListener('touchmove', function (e) {
                if (startX === null) return;
                var dx = e.touches[0].clientX - startX;
                var dy = e.touches[0].clientY - startY;
                // Only a decisive horizontal drag closes it — vertical drags scroll.
                if (dx < -60 && Math.abs(dx) > Math.abs(dy)) {
                    Drawer.close();
                    startX = null;
                }
            }, { passive: true });

            Drawer.el.addEventListener('touchend', function () { startX = null; }, { passive: true });
        },

        init: function () {
            Drawer.el = document.getElementById('av-sidebar');
            Drawer.backdrop = document.getElementById('av-backdrop');
            if (!Drawer.el) return;

            document.querySelectorAll('[data-drawer-open]').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    Drawer.toggle();
                });
            });

            document.querySelectorAll('[data-drawer-close]').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    Drawer.close();
                });
            });

            if (Drawer.backdrop) {
                Drawer.backdrop.addEventListener('click', Drawer.close);
            }

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') Drawer.close();
                Drawer.trapFocus(e);
            });

            // Following a link should not leave the drawer covering the new page.
            Drawer.el.addEventListener('click', function (e) {
                var link = e.target.closest('a[href]');
                if (link && !link.getAttribute('href').startsWith('#') && isMobile()) {
                    Drawer.close();
                }
            });

            Drawer.initSwipe();
        }
    };

    /* -------------------------------------------------------------------------
       Navigation: accordions, active state, search
       ---------------------------------------------------------------------- */
    var Nav = {
        key: 'av-nav-open',

        openGroups: function () {
            try { return JSON.parse(localStorage.getItem(Nav.key) || '[]'); } catch (e) { return []; }
        },

        remember: function (id, open) {
            var list = Nav.openGroups().filter(function (x) { return x !== id; });
            if (open) list.push(id);
            try { localStorage.setItem(Nav.key, JSON.stringify(list)); } catch (e) { /* noop */ }
        },

        initAccordions: function () {
            var remembered = Nav.openGroups();

            document.querySelectorAll('[data-nav-toggle]').forEach(function (btn) {
                var id = btn.getAttribute('data-nav-toggle');
                var panel = document.getElementById(id);
                if (!panel) return;

                // A group containing the current page always starts open.
                var shouldOpen = btn.classList.contains('is-active') || remembered.indexOf(id) !== -1;
                panel.classList.toggle('is-open', shouldOpen);
                btn.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');

                btn.addEventListener('click', function () {
                    var open = !panel.classList.contains('is-open');
                    panel.classList.toggle('is-open', open);
                    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                    Nav.remember(id, open);
                });
            });
        },

        initSearch: function () {
            var input = document.getElementById('av-nav-search');
            var nav = document.getElementById('av-nav');
            if (!input || !nav) return;

            var empty = nav.querySelector('.av-nav__empty');

            var run = function () {
                var q = input.value.trim().toLowerCase();
                var hits = 0;

                nav.querySelectorAll('.av-nav__item').forEach(function (item) {
                    var text = (item.getAttribute('data-search') || item.textContent).toLowerCase();
                    var match = !q || text.indexOf(q) !== -1;
                    item.hidden = !match;
                    if (match) hits++;

                    // While searching, expand every group so matches are reachable.
                    var panel = item.querySelector('.av-nav__sub');
                    var toggle = item.querySelector('[data-nav-toggle]');
                    if (panel && q) {
                        panel.classList.add('is-open');
                        if (toggle) toggle.setAttribute('aria-expanded', 'true');
                    }
                });

                nav.querySelectorAll('.av-nav__group').forEach(function (group) {
                    var visible = group.querySelectorAll('.av-nav__item:not([hidden])').length;
                    group.hidden = visible === 0;
                });

                nav.classList.toggle('is-filtered', q.length > 0);
                if (empty) empty.classList.toggle('is-shown', hits === 0);
            };

            input.addEventListener('input', run);
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    input.value = '';
                    run();
                    input.blur();
                }
            });
        },

        init: function () {
            Nav.initAccordions();
            Nav.initSearch();

            // Scroll the active item into view — long menus otherwise open blind.
            var active = document.querySelector('#av-nav .is-active');
            if (active) {
                var nav = document.getElementById('av-nav');
                if (nav && active.offsetTop > nav.clientHeight - 80) {
                    nav.scrollTop = active.offsetTop - nav.clientHeight / 2;
                }
            }
        }
    };

    /* -------------------------------------------------------------------------
       Tables: stacked-card mode on narrow screens
       ---------------------------------------------------------------------- */
    var Tables = {
        // Columns that hold controls or media read better full width.
        wideLabel: /^(action|actions|image|images|photo|picture|thumbnail|manage|options|controls)$/i,

        // Row-number columns carry no meaning once rows are cards.
        indexLabel: /^(#|no\.?|s\/n|sl\.?\s*no\.?|sr\.?\s*no\.?|serial|serial\s*no\.?|index)$/i,

        // Which column should headline the card. Without this the first text
        // column wins, which on the product list means the card is titled
        // "Firewoods" (the category) while the product name is buried three
        // rows down.
        titleLabel: /^(name|title|product|product\s*name|order\s*#|order\s*number|customer|customer\s*name|full\s*name|email|code|coupon\s*code|subject|question|zone|postcode|suburb)$/i,

        headings: function (table) {
            var row = table.querySelector('thead tr:last-child');
            if (!row) return [];
            return Array.prototype.map.call(row.children, function (th) {
                // Strip DataTables' sort-order helper text.
                return (th.textContent || '').replace(/\s+/g, ' ').trim();
            });
        },

        isWide: function (cell, label) {
            if (Tables.wideLabel.test(label)) return true;
            if (cell.querySelectorAll('.btn, button, form').length > 1) return true;
            // Only genuinely large media earns its own full-width row. Measure
            // what is on screen, not the file's intrinsic size — a 60px
            // thumbnail of a 600px source is still a 60px thumbnail, and
            // treating it as large made every product card a screen and a half.
            var img = cell.querySelector('img');
            if (img && img.getBoundingClientRect().width > 96) return true;
            return false;
        },

        isCheck: function (cell) {
            var input = cell.querySelector('input[type="checkbox"]');
            return !!input && cell.textContent.trim() === '';
        },

        // A cell that is nothing but a status chip belongs next to the title.
        isPill: function (cell) {
            if (!cell.querySelector('.badge, .av-pill')) return false;
            return cell.textContent.trim().length <= 24;
        },

        /**
         * Classify each cell so the CSS can lay the row out as a card:
         * a title line with its status chip, then labelled detail rows, then
         * the actions. Ordering is done with flex `order`, never by moving
         * nodes — DataTables owns this DOM and rebuilds it on every draw.
         */
        /**
         * Pick the column index that should headline each card: a recognised
         * name-ish header if there is one, otherwise the first plain text
         * column that is not an index, checkbox, media or action column.
         */
        titleIndex: function (tr, heads) {
            var preferred = -1;
            var fallback = -1;

            Array.prototype.forEach.call(tr.children, function (cell, i) {
                if (cell.colSpan > 1) return;
                if (preferred !== -1) return;

                var label = heads[i] || '';

                if (Tables.isCheck(cell) || Tables.indexLabel.test(label)) return;
                if (Tables.isWide(cell, label) || Tables.isPill(cell)) return;
                if (cell.textContent.trim() === '') return;

                if (Tables.titleLabel.test(label)) {
                    preferred = i;
                } else if (fallback === -1) {
                    fallback = i;
                }
            });

            return preferred !== -1 ? preferred : fallback;
        },

        classify: function (tr, heads) {
            var titleAt = Tables.titleIndex(tr, heads);

            Array.prototype.forEach.call(tr.children, function (cell, i) {
                if (cell.colSpan > 1) return;

                var label = heads[i] || '';

                cell.classList.remove(
                    'av-cell-index', 'av-cell-check', 'av-cell-pill',
                    'av-cell-title', 'av-cell-wide'
                );

                if (Tables.isCheck(cell)) {
                    cell.classList.add('av-cell-check');
                    return;
                }

                if (Tables.indexLabel.test(label)) {
                    cell.classList.add('av-cell-index');
                    return;
                }

                if (i === titleAt) {
                    cell.classList.add('av-cell-title');
                    return;
                }

                if (label) cell.setAttribute('data-label', label);

                if (Tables.isWide(cell, label)) {
                    cell.classList.add('av-cell-wide');
                    return;
                }

                if (Tables.isPill(cell)) {
                    cell.classList.add('av-cell-pill');
                }
            });
        },

        decorate: function (table) {
            if (!table || table.hasAttribute('data-no-cards')) return;

            var heads = Tables.headings(table);
            if (heads.length < 3) return;

            table.classList.add('av-cards');

            // Let the scroll wrappers expand so stacked rows are not clipped.
            ['.table-responsive', '.dataTables_scrollBody', '.dataTables_scroll', '.dataTables_wrapper']
                .forEach(function (sel) {
                    var host = table.closest(sel);
                    if (host) host.classList.add('av-cards-host');
                });

            table.querySelectorAll('tbody tr').forEach(function (tr) {
                Tables.classify(tr, heads);
            });
        },

        undecorate: function (table) {
            table.classList.remove('av-cards');
        },

        refresh: function () {
            var tables = document.querySelectorAll('table');
            if (isNarrow()) {
                tables.forEach(Tables.decorate);
            } else {
                tables.forEach(Tables.undecorate);
            }
        },

        /**
         * Several pages build table bodies with hand-rolled AJAX rather than
         * DataTables, so there is no draw event to hook. Watch for new rows.
         */
        observe: function () {
            if (!window.MutationObserver) return;

            var pending = null;

            var observer = new MutationObserver(function (records) {
                if (!isNarrow()) return;

                var touched = false;

                for (var i = 0; i < records.length; i++) {
                    var target = records[i].target;
                    if (target && target.closest && target.closest('table')) {
                        touched = true;
                        break;
                    }
                }

                if (!touched) return;

                clearTimeout(pending);
                pending = setTimeout(function () {
                    Tables.refresh();
                    Extras.labelIconButtons();
                }, 60);
            });

            observer.observe(document.body, { childList: true, subtree: true });
        },

        init: function () {
            // DataTables is initialised per page; set the shared defaults first.
            if (window.jQuery && jQuery.fn.dataTable) {
                jQuery.extend(true, jQuery.fn.dataTable.defaults, {
                    responsive: false,
                    // Horizontal scroll is a desktop affordance. On phones the
                    // rows become cards instead, which needs normal layout.
                    scrollX: !isNarrow(),
                    autoWidth: false,
                    language: {
                        search: '',
                        searchPlaceholder: 'Search…',
                        lengthMenu: 'Show _MENU_',
                        info: '_START_–_END_ of _TOTAL_',
                        infoEmpty: 'No records',
                        emptyTable: 'Nothing here yet',
                        zeroRecords: 'No matching records',
                        paginate: { previous: 'Prev', next: 'Next' }
                    }
                });

                jQuery(document).on('draw.dt init.dt', function (e) {
                    if (isNarrow()) Tables.decorate(e.target);
                    Extras.labelIconButtons();
                });
            }

            Tables.refresh();
            Tables.observe();
        }
    };

    /* -------------------------------------------------------------------------
       Bottom sheets

       A phone has no room for a filter rail down the side. Markup stays where
       it is; on narrow screens the panel is lifted into a sheet that slides up
       from the bottom, which is where a thumb already is.

           <button data-sheet-open="oc-filters">Filters</button>
           <div class="av-sheet" id="oc-filters"> … </div>
       ---------------------------------------------------------------------- */
    var Sheet = {
        open: function (id) {
            var sheet = document.getElementById(id);
            if (!sheet) return;

            sheet.classList.add('is-open');
            document.body.classList.add('av-lock');

            var backdrop = document.getElementById('av-sheet-backdrop');
            if (backdrop) backdrop.classList.add('is-open');

            var first = sheet.querySelector('input, select, button');
            if (first && !isMobile()) first.focus({ preventScroll: true });
        },

        close: function (sheet) {
            (sheet ? [sheet] : document.querySelectorAll('.av-sheet.is-open'))
                .forEach(function (el) { el.classList.remove('is-open'); });

            document.body.classList.remove('av-lock');

            var backdrop = document.getElementById('av-sheet-backdrop');
            if (backdrop) backdrop.classList.remove('is-open');
        },

        /** Reflect how many filters are active on the trigger button. */
        count: function (id, n) {
            document.querySelectorAll('[data-sheet-open="' + id + '"]').forEach(function (btn) {
                var badge = btn.querySelector('.av-sheet-count');

                if (!n) {
                    if (badge) badge.remove();
                    btn.classList.remove('is-filtered');
                    return;
                }

                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'av-sheet-count';
                    btn.appendChild(badge);
                }

                badge.textContent = n;
                btn.classList.add('is-filtered');
            });
        },

        init: function () {
            if (!document.querySelector('.av-sheet')) return;

            if (!document.getElementById('av-sheet-backdrop')) {
                var backdrop = document.createElement('div');
                backdrop.id = 'av-sheet-backdrop';
                backdrop.className = 'av-sheet-backdrop';
                backdrop.addEventListener('click', function () { Sheet.close(); });
                document.body.appendChild(backdrop);
            }

            document.addEventListener('click', function (e) {
                var opener = e.target.closest('[data-sheet-open]');
                if (opener) {
                    e.preventDefault();
                    Sheet.open(opener.getAttribute('data-sheet-open'));
                    return;
                }

                if (e.target.closest('[data-sheet-close]')) {
                    e.preventDefault();
                    Sheet.close();
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') Sheet.close();
            });

            // A sheet left open across the desktop breakpoint would trap scroll.
            window.addEventListener('resize', function () {
                if (!isMobile()) Sheet.close();
            });
        }
    };

    /* -------------------------------------------------------------------------
       Small conveniences
       ---------------------------------------------------------------------- */
    var Extras = {
        backToTop: function () {
            var btn = document.getElementById('av-top');
            if (!btn) return;

            var onScroll = function () {
                btn.classList.toggle('is-shown', window.scrollY > 400);
            };

            window.addEventListener('scroll', onScroll, { passive: true });
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
            onScroll();
        },

        /**
         * Android soft keyboard.
         *
         * Chrome on Android shrinks the visual viewport but not the layout
         * viewport, so `position: fixed` bars stay pinned to the bottom of the
         * *layout* viewport — i.e. they end up hidden behind the keyboard, or
         * floating across the middle of the screen. Collapse them while the
         * keyboard is up so the field being typed into stays visible.
         */
        keyboard: function () {
            var vv = window.visualViewport;
            if (!vv) return;

            var baseline = vv.height;

            var check = function () {
                // A drop of more than a quarter of the viewport is a keyboard,
                // not the address bar collapsing.
                var open = (baseline - vv.height) > baseline * 0.25;
                document.body.classList.toggle('av-keyboard', open);
            };

            vv.addEventListener('resize', check);

            // Rotation and address-bar changes reset what "full height" means.
            window.addEventListener('orientationchange', function () {
                setTimeout(function () {
                    baseline = vv.height;
                    check();
                }, 300);
            });

            document.addEventListener('focusout', function () {
                setTimeout(function () {
                    if (!document.activeElement ||
                        !document.activeElement.matches('input, textarea, select')) {
                        document.body.classList.remove('av-keyboard');
                    }
                }, 100);
            });
        },

        /**
         * Route legacy inline confirm() calls through SweetAlert.
         *
         * Two dozen pages still use onsubmit="return confirm('Delete this?')".
         * The native Android dialog is small, unlabelled and easy to mis-tap on
         * a destructive action. Intercepting in the capture phase lets us stop
         * the inline handler before it runs, without editing those pages.
         */
        confirmDialogs: function () {
            if (!window.Swal) return;

            var ask = function (el, attr, message, run) {
                Swal.fire({
                    title: message || 'Are you sure?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, continue',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true
                }).then(function (result) {
                    if (!result.isConfirmed) return;
                    // Drop the inline handler so the retry is not intercepted.
                    el.removeAttribute(attr);
                    run();
                });
            };

            var messageFrom = function (source) {
                var match = /confirm\s*\(\s*(['"])([\s\S]*?)\1/.exec(source || '');
                return match ? match[2].replace(/\\(['"])/g, '$1') : null;
            };

            document.addEventListener('submit', function (e) {
                var form = e.target;
                var attr = form.getAttribute && form.getAttribute('onsubmit');
                if (!attr || attr.indexOf('confirm(') === -1) return;

                e.preventDefault();
                e.stopImmediatePropagation();

                ask(form, 'onsubmit', messageFrom(attr), function () {
                    form.submit();
                });
            }, true);

            document.addEventListener('click', function (e) {
                var el = e.target.closest('[onclick*="confirm("]');
                if (!el) return;

                e.preventDefault();
                e.stopImmediatePropagation();

                ask(el, 'onclick', messageFrom(el.getAttribute('onclick')), function () {
                    el.click();
                });
            }, true);
        },

        /**
         * Give icon-only buttons a visible word on narrow screens. A bare glyph
         * is a guessing game, and 32px of trash icon next to 32px of edit icon
         * is how records get deleted by accident on a phone.
         */
        labelIconButtons: function () {
            if (!isNarrow()) return;

            document.querySelectorAll('.btn[title], .av-iconbtn[title]').forEach(function (btn) {
                if (btn.querySelector('.av-btn-label')) return;
                if (!btn.querySelector('i, svg')) return;

                // Text held *inside* the icon element (an emoji or a ligature)
                // still counts as an icon, not as a label.
                var own = Array.prototype.reduce.call(btn.childNodes, function (acc, node) {
                    if (node.nodeType === 3) return acc + node.textContent;
                    if (node.nodeType === 1 && !node.matches('i, svg')) return acc + node.textContent;
                    return acc;
                }, '');

                if (own.trim() !== '') return;

                var label = btn.getAttribute('title').trim();
                if (!label) return;

                // A title attribute is a tooltip, not a caption — some are a
                // full sentence ("Send silent test ping to confirm device still
                // receives notifications"). Rendering those as button text blew
                // the row out to 458px on a 360px screen. Only short, caption-
                // like titles get promoted; the rest stay as tooltips.
                if (label.length > 18 || /[.!?]/.test(label)) return;

                var span = document.createElement('span');
                span.className = 'av-btn-label ms-1';
                span.textContent = label;
                btn.appendChild(span);
                btn.classList.remove('btn-icon');
            });
        },

        /**
         * Legacy pages print their title twice: once in the page header and
         * again in the card header immediately below it ("Product List" over
         * "Product List"). Drop the second one, keeping whatever action button
         * sits alongside it.
         */
        dedupeTitles: function () {
            var heading = document.querySelector('.av-page-title, .page-title');
            if (!heading) return;

            var pageTitle = heading.textContent.trim().toLowerCase();
            if (!pageTitle) return;

            document.querySelectorAll('.card-header .card-title, .av-card__head .av-card__title')
                .forEach(function (title) {
                    if (title.textContent.trim().toLowerCase() !== pageTitle) return;

                    var head = title.closest('.card-header, .av-card__head');
                    title.hidden = true;

                    // With the text gone the header would collapse to a stray
                    // bar; push the remaining control to the right instead.
                    if (head) head.classList.add('av-head-dedup');
                });
        },

        // The bar title only appears once the page's own <h1> has scrolled off.
        stickyTitle: function () {
            var bar = document.querySelector('.av-topbar');
            if (!bar) return;

            var onScroll = function () {
                bar.classList.toggle('is-scrolled', window.scrollY > 72);
            };

            window.addEventListener('scroll', onScroll, { passive: true });
            onScroll();
        },

        // A double-tapped submit button on a slow mobile connection is the most
        // common source of duplicate records in this admin.
        guardDoubleSubmit: function () {
            document.addEventListener('submit', function (e) {
                var form = e.target;
                if (form.hasAttribute('data-allow-resubmit')) return;
                if (form.dataset.avSubmitting === '1') {
                    e.preventDefault();
                    return;
                }
                form.dataset.avSubmitting = '1';
                form.querySelectorAll('button[type="submit"]').forEach(function (btn) {
                    setTimeout(function () { btn.disabled = true; }, 0);
                });
                // Validation failures and back-navigation must not leave it stuck.
                setTimeout(function () {
                    form.dataset.avSubmitting = '';
                    form.querySelectorAll('button[type="submit"]').forEach(function (btn) {
                        btn.disabled = false;
                    });
                }, 8000);
            }, true);
        },

        collapsibleFilters: function () {
            document.querySelectorAll('[data-filters-toggle]').forEach(function (btn) {
                var panel = document.getElementById(btn.getAttribute('data-filters-toggle'));
                if (!panel) return;
                btn.addEventListener('click', function () {
                    var open = panel.style.display === 'none' || !panel.style.display;
                    panel.style.display = open ? 'block' : 'none';
                    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                });
                if (isMobile()) panel.style.display = 'none';
            });
        },

        toastr: function () {
            if (!window.toastr) return;
            toastr.options = {
                positionClass: isMobile() ? 'toast-bottom-full-width' : 'toast-top-right',
                closeButton: true,
                progressBar: true,
                newestOnTop: true,
                timeOut: 4000,
                preventDuplicates: true
            };
        },

        loader: function () {
            var loader = document.getElementById('av-loader');
            if (!loader) return;
            loader.classList.add('is-hidden');
            setTimeout(function () { loader.remove(); }, 250);
        }
    };

    /* -------------------------------------------------------------------------
       Boot
       ---------------------------------------------------------------------- */
    function boot() {
        Theme.init();
        Drawer.init();
        Nav.init();
        Tables.init();
        Extras.backToTop();
        Extras.dedupeTitles();
        Extras.stickyTitle();
        Extras.guardDoubleSubmit();
        Extras.collapsibleFilters();
        Extras.keyboard();
        Extras.confirmDialogs();
        Extras.labelIconButtons();
        Extras.toastr();
        Extras.loader();
        Sheet.init();

        var lastNarrow = isNarrow();
        var resizeTimer;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                if (!isMobile()) Drawer.close();
                if (isNarrow() !== lastNarrow) {
                    lastNarrow = isNarrow();
                    Tables.refresh();
                    Extras.labelIconButtons();
                }
            }, 150);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    /**
     * Normalise an image value into a usable <img src>.
     *
     * Models run their image column through the image_asset() helper, which
     * returns an *absolute* URL (and substitutes the shared placeholder when
     * the column is empty). Other endpoints serialise the raw relative path.
     * Pages that blindly wrote `'/' + value` turned the absolute ones into
     * "/https://host/…" and rendered a broken thumbnail on every row.
     */
    function imageSrc(value, fallback) {
        var placeholder = fallback || '/no_image/no_image.jpg';
        if (!value) return placeholder;
        if (/^(https?:)?\/\//i.test(value) || value.charAt(0) === '/') return value;
        return '/' + value;
    }

    window.AdminV2 = {
        Theme: Theme,
        Drawer: Drawer,
        Tables: Tables,
        Sheet: Sheet,
        Extras: Extras,
        imageSrc: imageSrc
    };
})();
