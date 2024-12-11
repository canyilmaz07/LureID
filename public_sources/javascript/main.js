document.addEventListener('DOMContentLoaded', function () {
    // DOM Elements
    const elements = {
        gridContainer: document.querySelector('.grid-container'),
        loadMoreBtn: document.querySelector('.load-more-button'),
        filterButton: document.querySelector('.filter-button'),
        filterOptions: document.querySelector('.filter-options'),
        hexInput: document.querySelector('.hex-input'),
        colorDropdown: document.querySelector('.color-dropdown'),
        colorOptions: document.querySelectorAll('.color-option'),
        searchType: document.querySelector('.search-type'),
        searchTypeTrigger: document.querySelector('.search-type-trigger'),
        searchTypeItems: document.querySelectorAll('.search-type-item'),
        viewType: document.querySelector('.view-type'),
        viewTypeTrigger: document.querySelector('.view-type-trigger'),
        viewTypeItems: document.querySelectorAll('.view-type-item'),
        cards: document.querySelectorAll('.card')
    };

    // Load More Configuration
    let offset = 15;
    const limit = 15;

    // Load More Functionality
    elements.loadMoreBtn.addEventListener('click', handleLoadMore);

    // Filter Functionality
    elements.filterButton.addEventListener('click', toggleFilterOptions);
    elements.hexInput.addEventListener('click', handleHexInputClick);

    // Color Options
    elements.colorOptions.forEach(option => {
        option.addEventListener('click', handleColorSelection);
    });

    // Search Type Functionality
    elements.searchTypeTrigger.addEventListener('click', toggleSearchType);
    elements.searchTypeItems.forEach(item => {
        item.addEventListener('click', handleSearchTypeSelection);
    });

    // View Type Functionality
    elements.viewTypeTrigger.addEventListener('click', toggleViewType);
    elements.viewTypeItems.forEach(item => {
        item.addEventListener('click', handleViewTypeSelection);
    });

    // Global Click Handler
    document.addEventListener('click', handleGlobalClick);

    // Initialize Card Animations
    initializeAllCardAnimations();

    // Handler Functions
    function handleLoadMore() {
        elements.loadMoreBtn.disabled = true;

        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `loadMore=1&offset=${offset}&limit=${limit}`
        })
            .then(response => response.json())
            .then(data => {
                elements.gridContainer.insertAdjacentHTML('beforeend', data.html);
                offset += limit;
                initializeCardAnimations(offset - limit);

                if (data.remaining <= 0) {
                    elements.loadMoreBtn.style.display = 'none';
                }
                elements.loadMoreBtn.disabled = false;
            })
            .catch(error => {
                console.error('Error:', error);
                elements.loadMoreBtn.disabled = false;
            });
    }

    function toggleFilterOptions() {
        const isHidden = elements.filterOptions.style.display === 'none' || !elements.filterOptions.style.display;
        elements.filterOptions.style.display = isHidden ? 'block' : 'none';
    }

    function handleHexInputClick(e) {
        e.stopPropagation();
        elements.colorDropdown.classList.toggle('active');
    }

    function handleColorSelection() {
        const color = this.dataset.color;
        elements.hexInput.value = color;
        elements.colorDropdown.classList.remove('active');
    }

    function toggleSearchType() {
        elements.searchType.classList.toggle('active');
    }

    function handleSearchTypeSelection() {
        elements.searchTypeItems.forEach(i => i.classList.remove('active'));
        this.classList.add('active');
        elements.searchTypeTrigger.querySelector('span').textContent = this.textContent;
        elements.searchType.classList.remove('active');
    }

    function toggleViewType() {
        elements.viewType.classList.toggle('active');
    }

    function handleViewTypeSelection() {
        elements.viewTypeItems.forEach(i => i.classList.remove('active'));
        this.classList.add('active');
        elements.viewTypeTrigger.querySelector('span').textContent = this.textContent;
        elements.viewType.classList.remove('active');
    }

    function handleGlobalClick(e) {
        // Close dropdowns when clicking outside
        if (!e.target.closest('.search-type')) {
            elements.searchType.classList.remove('active');
        }
        if (!e.target.closest('.color-picker-container')) {
            elements.colorDropdown.classList.remove('active');
        }
    }

    function initializeCardAnimations(startIndex = 0) {
        const newCards = document.querySelectorAll('.card');

        newCards.forEach((card, index) => {
            if (index >= startIndex) {
                setupCardAnimation(card);
            }
        });
    }

    function initializeAllCardAnimations() {
        elements.cards.forEach(card => setupCardAnimation(card));
    }

    function setupCardAnimation(card) {
        const content = card.querySelector('.card-content');
        const meta = content.querySelector('.card-meta');
        const title = content.querySelector('.card-title');
        const stats = content.querySelector('.stats-container');
        const tagBox = card.querySelector('.tag-box');

        // Initial states
        gsap.set([content, meta, title, stats], {
            opacity: 0,
            y: 20
        });

        gsap.set(tagBox, {
            opacity: 0,
            y: -10
        });

        // Create timeline
        const tl = gsap.timeline({ paused: true });

        tl.to(content, {
            opacity: 1,
            duration: 0.4,
            ease: "power4.inOut"
        })
            .to(meta, {
                opacity: 1,
                y: 0,
                duration: 0.5,
                ease: "power4.inOut"
            }, "-=0.2")
            .to(title, {
                opacity: 1,
                y: 0,
                duration: 0.5,
                ease: "power4.inOut"
            }, "-=0.3")
            .to(stats, {
                opacity: 1,
                y: 0,
                duration: 0.5,
                ease: "power4.inOut"
            }, "-=0.3")
            .to(tagBox, {
                opacity: 1,
                y: 0,
                duration: 0.4,
                ease: "power4.inOut"
            }, "-=0.5");

        // Mouse events
        card.addEventListener('mouseenter', () => tl.play());
        card.addEventListener('mouseleave', () => tl.reverse());
    }
});