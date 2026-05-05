document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('.sidebar');
    if (!sidebar) {
        return;
    }

    const apiPath = window.location.pathname.includes('/admin/') || window.location.pathname.includes('/user/')
        ? '../api_data.php?action=get_sidebar_counts'
        : 'api_data.php?action=get_sidebar_counts';

    const getBadgeHost = (link) => {
        let label = link.querySelector('.sidebar-link-label');
        if (label) {
            return label;
        }

        label = document.createElement('span');
        label.className = 'sidebar-link-label';

        const icon = link.querySelector('i');
        const linkText = Array.from(link.childNodes)
            .filter((node) => {
                return !(node.nodeType === Node.ELEMENT_NODE && (
                    node.tagName.toLowerCase() === 'i' ||
                    node.classList.contains('sidebar-badge')
                ));
            })
            .map((node) => node.textContent)
            .join(' ')
            .replace(/\s+/g, ' ')
            .trim();

        Array.from(link.childNodes).forEach((node) => {
            if (node !== icon) {
                node.remove();
            }
        });

        label.appendChild(document.createTextNode(linkText));

        if (icon && icon.nextSibling) {
            link.insertBefore(label, icon.nextSibling);
        } else {
            link.appendChild(label);
        }
        return label;
    };

    const setBadge = (linkSelector, count) => {
        const link = sidebar.querySelector(linkSelector);
        if (!link) {
            return;
        }

        getBadgeHost(link);
        let badge = link.querySelector('.sidebar-badge');
        const value = Number(count) || 0;

        if (value <= 0) {
            if (badge) {
                badge.remove();
            }
            link.classList.remove('has-sidebar-badge');
            return;
        }

        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'sidebar-badge sidebar-badge-count';
        }

        badge.textContent = value > 99 ? '99+' : String(value);
        badge.setAttribute('aria-label', `${value} update${value === 1 ? '' : 's'}`);
        link.classList.add('has-sidebar-badge');
        link.prepend(badge);
    };

    const updateSidebarBadges = () => {
        fetch(apiPath, { cache: 'no-store' })
            .then(response => response.ok ? response.json() : null)
            .then(data => {
                if (!data || !data.sidebar) {
                    return;
                }

                setBadge('a[href="reservations.php"]', data.sidebar.pending_reservations);
                setBadge('a[href="cart.php"]', data.sidebar.cart_items);
                setBadge('a[href="pickup_calendar.php"]', data.sidebar.today_pickups);
            })
            .catch(() => {});
    };

    updateSidebarBadges();
    setInterval(updateSidebarBadges, 5000);
});
