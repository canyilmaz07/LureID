// Center search HTML'ini güncelleyelim
document.querySelector('.center-search').innerHTML = `
<div class="ctrl-box">CTRL</div>
<span>Arama</span>
<div class="search-container" style="display: none">
<input type="text" class="search-input" placeholder="Aramak için yazın...">
<img src="/sources/icons/bulk/search-normal.svg" alt="search" class="search-icon white-icon">
</div>
`;

// CSS eklemeleri
const style = document.createElement('style');
style.textContent = `
.center-search {
min-width: 260px;
display: flex;
justify-content: center;
align-items: center;
}

.search-container {
position: absolute;
left: 0;
top: 0;
width: 100%;
height: 100%;
opacity: 0;
transform: scale(0.95);
}

.search-input {
width: 100%;
height: 100%;
background: rgba(0, 0, 0, 0.05);
border: none;
border-radius: 8px;
padding: 0 40px 0 12px;
font-size: 14px;
color: #000;
outline: none;
}

.search-input::placeholder {
color: rgba(0, 0, 0, 0.5);
}

.search-icon {
position: absolute;
right: 12px;
top: 50%;
transform: translateY(-50%);
width: 20px;
height: 20px;
opacity: 0.6;
pointer-events: none;
}
`;
document.head.appendChild(style);

// Arama kontrolü için event listener'ları ve animasyonları güncelleyelim
document.addEventListener('DOMContentLoaded', () => {
    const centerSearch = document.querySelector('.center-search');
    const ctrlBox = centerSearch.querySelector('.ctrl-box');
    const searchText = centerSearch.querySelector('span');
    const searchContainer = centerSearch.querySelector('.search-container');
    const searchInput = centerSearch.querySelector('.search-input');
    let isSearchActive = false;

    function openSearch() {
        if (!isSearchActive) {
            const tl = gsap.timeline();

            // CTRL box ve text animasyonu
            tl.to([ctrlBox, searchText], {
                opacity: 0,
                scale: 0.8,
                duration: 0.2,
                ease: 'power2.in',
                onComplete: () => {
                    ctrlBox.style.display = 'none';
                    searchText.style.display = 'none';
                    searchContainer.style.display = 'block';
                }
            })
                // Search container animasyonu
                .fromTo(searchContainer, {
                    opacity: 0,
                    scale: 0.95,
                    display: 'block'
                }, {
                    opacity: 1,
                    scale: 1,
                    duration: 0.3,
                    ease: 'power2.out',
                    onComplete: () => {
                        searchInput.focus();
                    }
                });

            isSearchActive = true;
        }
    }

    let searchTimeout;
    let isSearchDropdownOpen = false;

    function openSearchDropdown() {
        const searchDropdown = document.querySelector('.search-dropdown');
        gsap.to(searchDropdown, {
            opacity: 1,
            visibility: 'visible',
            duration: 0.3,
            ease: 'power3.out'
        });
        isSearchDropdownOpen = true;
    }

    function closeSearchDropdown() {
        const searchDropdown = document.querySelector('.search-dropdown');
        gsap.to(searchDropdown, {
            opacity: 0,
            visibility: 'hidden',
            duration: 0.2,
            ease: 'power3.in',
            onComplete: () => {
                document.querySelector('#searchResults').innerHTML = '';
            }
        });
        isSearchDropdownOpen = false;
    }

    searchInput.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        const query = e.target.value.trim();

        if (query.length < 2) {
            closeSearchDropdown();
            return;
        }

        searchTimeout = setTimeout(() => {
            fetch(`/public_sources/php/search_dropdown.php?query=${encodeURIComponent(query)}`)
                .then(async response => {
                    if (!response.ok) {
                        const errorData = await response.json();
                        throw new Error(errorData.error || 'Network response was not ok');
                    }
                    return response.json();
                })
                .then(response => {
                    const searchResults = document.querySelector('#searchResults');

                    if (response.status === 'success' && response.data.length > 0) {
                        const html = response.data.map((user, index) => `
    ${index !== 0 ? '<div class="search-result-divider"></div>' : ''}
    <a href="/${user.username}" class="search-result-item">
        <img src="${user.profile_photo_url === 'undefined' ?
                                '/public/sources/defaults/avatar.jpg' :
                                '/public/' + user.profile_photo_url}" 
             alt="${user.username}" 
             class="search-result-avatar"
             onerror="this.src='/public/sources/defaults/avatar.jpg'">
        <div class="search-result-info">
            <div class="search-result-name">${user.full_name}</div>
            <div class="search-result-username">@${user.username}</div>
        </div>
        <img src="/sources/icons/bulk/arrow-right.svg" alt="arrow" class="search-result-arrow">
    </a>
`).join('');

                        searchResults.innerHTML = html;
                    } else {
                        searchResults.innerHTML = '<div class="no-results">Kullanıcı bulunamadı</div>';
                    }

                    if (!isSearchDropdownOpen) {
                        openSearchDropdown();
                    }
                })
                .catch(error => {
                    console.error('Search error:', error.message);
                    const searchResults = document.querySelector('#searchResults');
                    searchResults.innerHTML = `<div class="no-results">Hata: ${error.message}</div>`;
                });
        }, 300);
    });

    // Sayfa tıklaması ile dropdown'ı kapat
    document.addEventListener('click', (e) => {
        if (isSearchDropdownOpen &&
            !e.target.closest('.search-dropdown') &&
            !e.target.closest('.search-input')) {
            closeSearchDropdown();
        }
    });

    // ESC tuşu ile kapatma kısmını güncelle
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (isSearchActive) {
                closeSearch();
                searchInput.value = '';
            }
            if (isSearchDropdownOpen) {
                closeSearchDropdown();
            }
        }
    });

    function closeSearch() {
        if (isSearchActive) {
            const tl = gsap.timeline();

            // Search container animasyonu
            tl.to(searchContainer, {
                opacity: 0,
                scale: 0.95,
                duration: 0.2,
                ease: 'power2.in',
                onComplete: () => {
                    searchContainer.style.display = 'none';
                    ctrlBox.style.display = 'block';
                    searchText.style.display = 'block';
                }
            })
                // CTRL box ve text animasyonu
                .fromTo([ctrlBox, searchText], {
                    opacity: 0,
                    scale: 0.8,
                }, {
                    opacity: 1,
                    scale: 1,
                    duration: 0.3,
                    ease: 'power2.out'
                });

            isSearchActive = false;
        }
    }

    // CTRL tuşu kontrolü
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Control') {
            if (!isSearchActive) {
                openSearch();
            } else {
                closeSearch();
                searchInput.value = ''; // Input'u temizle
            }
            e.preventDefault();
        }

        // ESC tuşu ile kapatma
        if (e.key === 'Escape' && isSearchActive) {
            closeSearch();
            searchInput.value = ''; // Input'u temizle
        }
    });

    // Input dışına tıklanınca kapatma
    document.addEventListener('click', (e) => {
        if (isSearchActive && !centerSearch.contains(e.target)) {
            closeSearch();
            searchInput.value = ''; // Input'u temizle
        }
    });

    // Center search tıklama ile açma
    centerSearch.addEventListener('click', () => {
        if (!isSearchActive) {
            openSearch();
        }
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const tl = gsap.timeline();
    const menuItems = document.querySelectorAll('.menu-item');
    const menuContainer = document.querySelector('.menu-container');
    const submenuContainer = document.querySelector('.submenu-container');
    const submenuLayouts = document.querySelectorAll('.submenu-layout');
    const menuWrapper = document.querySelector('.menu-wrapper');
    let activeButton = null;
    let isSubmenuOpen = false;

    // İlk animasyon
    gsap.set(menuWrapper, {
        opacity: 0,
        y: -80
    });

    // Başlangıçta login butonlarını gizle
    gsap.set(['.register-btn', '.login-btn'], {
        opacity: 0,
        y: -20
    });

    tl.to(menuWrapper, {
        opacity: 1,
        y: 0,
        duration: 0.6,
        ease: 'power3.inOut'
    })
        .to('.menu-container', {
            opacity: 1,
            duration: 0.4,
            ease: 'power3.out'
        })
        .to('.lure-text', {
            opacity: 1,
            scale: 1,
            duration: 0.4,
            ease: 'power3.out'
        })
        .to('.left-menu', {
            visibility: 'visible',
            opacity: 1,
            duration: 0.4
        })
        .to('.menu-item', {
            opacity: 1,
            y: 0,
            duration: 0.5,
            stagger: 0.1,
            ease: 'power3.out'
        }, '-=0.2')
        // Login butonları sırayla gelsin
        .to('.register-btn', {
            opacity: 1,
            y: 0,
            duration: 0.3,
            ease: 'power3.out'
        })
        .to('.login-btn', {
            opacity: 1,
            y: 0,
            duration: 0.3,
            ease: 'power3.out'
        })
        .add(() => {
            setTimeout(() => {
                gsap.to('.lure-text', {
                    y: '100%',
                    opacity: 0,
                    duration: 0.5,
                    ease: 'power3.in',
                    onComplete: () => {
                        gsap.fromTo('.center-search',
                            {
                                y: '-100%',
                                opacity: 0,
                                visibility: 'visible'
                            },
                            {
                                y: 0,
                                opacity: 1,
                                duration: 0.5,
                                ease: 'power3.out'
                            }
                        );
                    }
                });
            }, 2000);
        });

    // Submenu helpers
    function getSubmenuHeight(layout) {
        // Reset any height restrictions temporarily to get true height
        layout.style.display = 'grid';
        const trueHeight = layout.offsetHeight;

        // Add padding for container (24px top + 24px bottom = 48px)
        return trueHeight + 48;
    }

    function animateSubmenu(button, layout) {
        // Layout'ları hemen gizle/göster
        submenuLayouts.forEach(l => {
            l.style.display = 'none';
            l.classList.remove('active');
        });
        layout.classList.add('active');
        layout.style.display = 'grid';

        const submenuHeight = getSubmenuHeight(layout);
        const headers = layout.querySelectorAll('.submenu-header');
        const items = layout.querySelectorAll('.submenu-item');

        // States'i hemen set et
        gsap.set([headers, items], {
            opacity: 0,
            y: -10
        });

        // Timeline'ı hızlandır
        const tl = gsap.timeline({ defaults: { ease: 'power3.out' } });
        button.classList.add('active');

        tl.to(['.menu-container', '.main-menu'], {
            borderRadius: '15px 15px 0 0',
            duration: 0.2, // 0.3'ten 0.2'ye düşürdük
        })
            .to(submenuContainer, {
                height: submenuHeight,
                opacity: 1,
                duration: 0.3, // 0.5'ten 0.3'e düşürdük
                ease: 'power2.out'
            })
            .to('.submenu', {
                opacity: 1,
                y: 0,
                duration: 0.2, // 0.3'ten 0.2'ye düşürdük
            })
            .to(headers, {
                opacity: 1,
                y: 0,
                duration: 0.2, // 0.3'ten 0.2'ye düşürdük
                stagger: 0.05 // 0.1'den 0.05'e düşürdük
            })
            .to(items, {
                opacity: 1,
                y: 0,
                duration: 0.2, // 0.3'ten 0.2'ye düşürdük
                stagger: {
                    each: 0.02, // 0.05'ten 0.02'ye düşürdük
                    grid: [items.length / 3, 3],
                    from: "start"
                }
            }, '-=0.1'); // Örtüşmeyi artırdık

        isSubmenuOpen = true;
        activeButton = button;
    }

    function closeSubmenu(button, callback) {
        const tl = gsap.timeline({
            onComplete: () => {
                submenuLayouts.forEach(layout => layout.classList.remove('active'));
                if (button) button.classList.remove('active');
                if (callback) callback();
            },
            defaults: { ease: 'power3.in' }
        });

        tl.to('.submenu-item', {
            opacity: 0,
            y: -10,
            duration: 0.2, // 0.3'ten 0.2'ye düşürdük
            stagger: 0.01 // 0.03'ten 0.01'e düşürdük
        })
            .to('.submenu-header', {
                opacity: 0,
                y: -10,
                duration: 0.2, // 0.3'ten 0.2'ye düşürdük
                stagger: 0.01 // 0.03'ten 0.01'e düşürdük
            }, '-=0.15')
            .to(submenuContainer, {
                height: 0,
                opacity: 0,
                duration: 0.3, // 0.5'ten 0.3'e düşürdük
                ease: 'power2.inOut'
            })
            .to(['.menu-container', '.main-menu'], {
                borderRadius: '15px',
                duration: 0.2 // 0.3'ten 0.2'ye düşürdük
            }, '-=0.1'); // Örtüşmeyi artırdık

        isSubmenuOpen = false;
        activeButton = null;
    }

    // Menu click handlers
    menuItems.forEach((button, index) => {
        if (index === 0) return; // Skip Ana Sayfa

        button.addEventListener('click', () => {
            const targetLayout = submenuLayouts[index - 1];

            if (isSubmenuOpen) {
                if (activeButton === button) {
                    closeSubmenu(button);
                } else {
                    closeSubmenu(activeButton, () => {
                        animateSubmenu(button, targetLayout);
                    });
                }
            } else {
                animateSubmenu(button, targetLayout);
            }
        });
    });

    // Close on outside click
    document.addEventListener('click', (e) => {
        if (isSubmenuOpen &&
            !e.target.closest('.submenu-container') &&
            !e.target.closest('.menu-item')) {
            closeSubmenu(activeButton);
        }
    });

    // Hover animations
    const hoverableElements = document.querySelectorAll('.menu-item, .center-search, .icon-item, .register-btn, .login-btn');
    hoverableElements.forEach(element => {
        element.addEventListener('mouseenter', () => {
            if (!element.classList.contains('active')) {
                gsap.to(element, {
                    scale: 1.1,
                    duration: 0.3,
                    ease: 'power2.out'
                });
            }
        });

        element.addEventListener('mouseleave', () => {
            if (!element.classList.contains('active')) {
                gsap.to(element, {
                    scale: 1,
                    duration: 0.3,
                    ease: 'power2.out'
                });
            }
        });
    });
    // Profile and Settings functionality
    const profileButton = document.querySelector('.profile');
    const profileDropdown = document.querySelector('.profile-dropdown');
    const settingsTrigger = document.querySelector('.settings-trigger');
    const settingsDropdown = document.querySelector('.settings-dropdown');
    const settingsItems = document.querySelectorAll('.setting-item');
    const settingsArrow = document.querySelector('.settings-arrow');
    const profileMenuItems = document.querySelectorAll('.profile-menu-item');
    let isProfileOpen = false;
    let isSettingsOpen = false;

    function openProfileMenu() {
        const tl = gsap.timeline();
        profileDropdown.style.visibility = 'visible';
        profileButton.classList.add('active');

        tl.to(profileDropdown, {
            opacity: 1,
            scale: 1,
            duration: 0.3,
            ease: 'back.out(1.7)'
        })
            .to(profileMenuItems, {
                opacity: 1,
                y: 0,
                duration: 0.2,
                stagger: 0.05,
                ease: 'power3.out'
            });

        isProfileOpen = true;
    }

    function closeProfileMenu() {
        const tl = gsap.timeline({
            onComplete: () => {
                profileDropdown.style.visibility = 'hidden';
                profileButton.classList.remove('active');
                if (isSettingsOpen) {
                    closeSettings(true);
                }
            }
        });

        tl.to(profileMenuItems, {
            opacity: 0,
            y: -10,
            duration: 0.2,
            stagger: 0.03,
            ease: 'power3.in'
        })
            .to(profileDropdown, {
                opacity: 0,
                scale: 0.95,
                duration: 0.2,
                ease: 'power3.in'
            });

        isProfileOpen = false;
    }

    profileButton.addEventListener('click', () => {
        if (isSubmenuOpen) {
            closeSubmenu(activeButton, () => {
                activeButton = null;
                isSubmenuOpen = false;
                openProfileMenu();
            });
        } else if (!isProfileOpen) {
            openProfileMenu();
        } else {
            closeProfileMenu();
        }
    });

    function openSettings() {
        const tl = gsap.timeline();
        settingsArrow.classList.add('active');

        tl.to(settingsDropdown, {
            height: 'auto',
            duration: 0.3,
            ease: 'power3.out'
        })
            .to(settingsItems, {
                opacity: 1,
                y: 0,
                duration: 0.2,
                stagger: 0.03,
                ease: 'power3.out'
            }, '-=0.1');

        isSettingsOpen = true;
    }

    function closeSettings(immediate = false) {
        const duration = immediate ? 0 : 0.2;
        const tl = gsap.timeline();
        settingsArrow.classList.remove('active');

        tl.to(settingsItems, {
            opacity: 0,
            y: -10,
            duration: duration,
            stagger: 0.02,
            ease: 'power3.in'
        })
            .to(settingsDropdown, {
                height: 0,
                duration: duration,
                ease: 'power3.in'
            });

        isSettingsOpen = false;
    }

    settingsTrigger.addEventListener('click', (e) => {
        e.stopPropagation();
        if (!isSettingsOpen) {
            openSettings();
        } else {
            closeSettings();
        }
    });

    // Close dropdowns on outside click
    document.addEventListener('click', (e) => {
        if (isProfileOpen &&
            !profileDropdown.contains(e.target) &&
            !profileButton.contains(e.target)) {
            closeProfileMenu();
        }
    });

    // Wallet functionality
    const walletButton = document.getElementById('walletIcon');
    const walletDropdown = document.querySelector('.wallet-dropdown');
    let isWalletOpen = false;

    function openWalletMenu() {
        walletDropdown.style.visibility = 'visible';
        updateWalletData().then(() => {
            const tl = gsap.timeline();

            gsap.set([
                '.wallet-header',
                '.wallet-balance',
                '.recent-transactions-title',
                '.transaction-item',
                '.view-all-link'
            ], {
                opacity: 0,
                y: -20
            });

            tl.to(walletDropdown, {
                opacity: 1,
                scale: 1,
                duration: 0.3,
                ease: 'back.out(1.7)'
            })
                .to('.wallet-header', {
                    opacity: 1,
                    y: 0,
                    duration: 0.3,
                    ease: 'power3.out'
                })
                .to('.wallet-balance', {
                    opacity: 1,
                    y: 0,
                    duration: 0.3,
                    stagger: 0.15,
                    ease: 'power3.out'
                })
                .to('.recent-transactions-title', {
                    opacity: 1,
                    y: 0,
                    duration: 0.3,
                    ease: 'power3.out'
                })
                .to('.transaction-item', {
                    opacity: 1,
                    y: 0,
                    duration: 0.3,
                    stagger: 0.1,
                    ease: 'power3.out'
                })
                .to('.view-all-link', {
                    opacity: 1,
                    y: 0,
                    duration: 0.3,
                    ease: 'power3.out'
                });
        });

        isWalletOpen = true;
    }

    function closeWalletMenu() {
        const tl = gsap.timeline({
            onComplete: () => {
                walletDropdown.style.visibility = 'hidden';
                gsap.set([
                    '.wallet-header',
                    '.wallet-balance',
                    '.recent-transactions-title',
                    '.transaction-item',
                    '.view-all-link'
                ], {
                    clearProps: "all"
                });
            }
        });

        tl.to('.view-all-link', {
            opacity: 0,
            y: -10,
            duration: 0.2,
            ease: 'power3.in'
        })
            .to('.transaction-item', {
                opacity: 0,
                y: -10,
                duration: 0.2,
                stagger: 0.05,
                ease: 'power3.in'
            }, '-=0.1')
            .to('.recent-transactions-title', {
                opacity: 0,
                y: -10,
                duration: 0.2,
                ease: 'power3.in'
            }, '-=0.1')
            .to('.wallet-balance', {
                opacity: 0,
                y: -10,
                duration: 0.2,
                stagger: 0.05,
                ease: 'power3.in'
            }, '-=0.1')
            .to('.wallet-header', {
                opacity: 0,
                y: -10,
                duration: 0.2,
                ease: 'power3.in'
            }, '-=0.1')
            .to(walletDropdown, {
                opacity: 0,
                scale: 0.95,
                duration: 0.2,
                ease: 'power3.in'
            }, '-=0.1');

        isWalletOpen = false;
    }

    walletButton.addEventListener('click', () => {
        if (isSubmenuOpen) {
            closeSubmenu(activeButton, () => {
                activeButton = null;
                isSubmenuOpen = false;
                openWalletMenu();
            });
        } else if (!isWalletOpen) {
            openWalletMenu();
        } else {
            closeWalletMenu();
        }
    });

    document.addEventListener('click', (e) => {
        if (isWalletOpen &&
            !walletDropdown.contains(e.target) &&
            !walletButton.contains(e.target)) {
            closeWalletMenu();
        }
    });

    // Hover animations for all menu items
    document.querySelectorAll('.submenu-item, .wallet-balance, .transaction-item, .view-all-link').forEach(item => {
        item.addEventListener('mouseenter', () => {
            gsap.to(item, {
                scale: 1.02,
                duration: 0.3,
                ease: 'power2.out'
            });
        });

        item.addEventListener('mouseleave', () => {
            gsap.to(item, {
                scale: 1,
                duration: 0.3,
                ease: 'power2.out'
            });
        });
    });

    function updateWalletData() {
        return fetch('components/wallet/get_wallet_data.php')
            .then(response => response.json())
            .then(data => {
                document.getElementById('dropdownBalance').textContent =
                    new Intl.NumberFormat('tr-TR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }).format(data.balance);

                document.getElementById('dropdownCoins').textContent =
                    new Intl.NumberFormat('tr-TR').format(data.coins);

                const transactionsHtml = data.recent_transactions
                    .slice(0, 3)
                    .map(transaction => {
                        let icon, amountClass, amountPrefix, description;

                        const isCoinTransaction = ['COINS_RECEIVED', 'COINS_USED'].includes(transaction.transaction_type);

                        switch (transaction.transaction_type) {
                            case 'COINS_RECEIVED':
                                icon = 'coin';
                                amountClass = 'positive';
                                amountPrefix = '+';
                                description = 'Jeton Alındı';
                                break;
                            case 'COINS_USED':
                                icon = 'coin';
                                amountClass = 'negative';
                                amountPrefix = '-';
                                description = 'Jeton Kullanıldı';
                                break;
                            case 'DEPOSIT':
                                icon = 'arrow-down';
                                amountClass = 'positive';
                                amountPrefix = '+';
                                description = 'Para Yatırma';
                                break;
                            case 'WITHDRAWAL':
                                icon = 'arrow-up';
                                amountClass = 'negative';
                                amountPrefix = '-';
                                description = 'Para Çekme';
                                break;
                            case 'TRANSFER':
                                if (transaction.sender_id == data.user_id) {
                                    icon = 'export';
                                    amountClass = 'negative';
                                    amountPrefix = '-';
                                    description = `Transfer: ${transaction.receiver_username}`;
                                } else {
                                    icon = 'import';
                                    amountClass = 'positive';
                                    amountPrefix = '+';
                                    description = `Transfer: ${transaction.sender_username}`;
                                }
                                break;
                            case 'PAYMENT':
                                icon = 'card';
                                amountClass = transaction.sender_id == data.user_id ? 'negative' : 'positive';
                                amountPrefix = transaction.sender_id == data.user_id ? '-' : '+';
                                description = transaction.description || 'Ödeme';
                                break;
                            default:
                                icon = 'refresh';
                                amountClass = transaction.sender_id == data.user_id ? 'negative' : 'positive';
                                amountPrefix = transaction.sender_id == data.user_id ? '-' : '+';
                                description = transaction.description || 'Diğer İşlem';
                        }

                        const formattedAmount = isCoinTransaction
                            ? `${amountPrefix}${parseInt(transaction.amount).toString()} 🪙`
                            : `${amountPrefix}₺${parseFloat(transaction.amount).toFixed(2)}`;

                        return `
    <div class="transaction-item">
        <div class="transaction-info">
            <img src="/sources/icons/bulk/${icon}.svg" alt="${transaction.transaction_type}" class="transaction-icon white-icon">
            <div>
                <span class="transaction-description">${description}</span>
                <div class="transaction-date text-xs text-gray-500">
                    ${new Date(transaction.created_at).toLocaleString('tr-TR')}
                </div>
            </div>
        </div>
        <div class="transaction-amount ${amountClass}">
            ${formattedAmount}
        </div>
    </div>
`;
                    }).join('');

                document.getElementById('dropdownTransactions').innerHTML = transactionsHtml;
            });
    }
});