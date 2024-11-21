<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Search Menu</title>
   <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
   <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
   <style>
       * {
           margin: 0;
           padding: 0;
           box-sizing: border-box;
           font-family: 'Poppins', sans-serif;
       }

       :root {
           --bg-color: #f9f9f9;
           --border-color: #E5E7EB;
           --text-color: #333;
           --search-bg: rgba(255, 255, 255, 0.98);
           --hover-bg: rgba(0, 0, 0, 0.03);
           --hover-bg-dark: rgba(0, 0, 0, 0.06); 
       }

       body {
           background: var(--bg-color);
       }

       .search-container {
           position: fixed;
           top: 20px;
           left: 50%;
           transform: translateX(-50%) translateY(-100px);
           width: 450px;
           opacity: 0;
           display: none;
       }

       .search-wrapper {
           position: relative;
           width: 100%;
       }

       .search-input {
           width: 100%;
           height: 45px;
           padding-left: 25px;
           padding-right: 55px;
           font-size: 13px;
           font-weight: 500;
           color: var(--text-color);
           background: var(--search-bg);
           border: 1px solid var(--border-color);
           border-radius: 8px;
           outline: none;
           transition: border-color 0.2s;
           box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
       }

       .search-input:focus {
           border-color: #000;
       }

       .search-icon {
           position: absolute;
           right: 25px;
           top: 50%;
           transform: translateY(-50%);
           width: 16px;
           height: 16px;
           pointer-events: none;
           opacity: 0.5;
       }

       .menu-section {
           background: var(--search-bg);
           border: 1px solid var(--border-color);
           border-radius: 8px;
           margin-top: 8px;
           padding: 12px;
           box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
       }

       .section-title {
           color: #666;
           font-size: 12px;
           font-weight: 500;
           margin-bottom: 8px;
           padding: 0 4px;
       }

       .search-item {
           display: flex;
           align-items: center;
           gap: 12px;
           padding: 8px;
           border-radius: 6px;
           cursor: pointer;
           transition: background 0.2s;
       }

       .search-item:hover {
           background: var(--hover-bg);
       }

       .search-item-icon {
           width: 48px;
           height: 48px;
           display: flex;
           align-items: center;
           justify-content: center;
           border-radius: 8px;
           background: var(--hover-bg);
       }

       .search-item-content {
           flex: 1;
       }

       .search-item-title {
           font-size: 13px;
           font-weight: 500;
           color: var(--text-color);
       }

       .search-item-subtitle {
           font-size: 12px;
           color: #666;
           margin-top: 2px;
       }

       @media screen and (max-width: 624px) {
           .search-container {
               width: calc(100% - 40px);
           }
           
           .search-input {
               height: 48px;
               font-size: 14px;
           }
       }
   </style>
</head>
<body>

<div class="search-container">
   <div class="search-wrapper">
       <input type="text" class="search-input" placeholder="Search...">
       <img src="sources/icons/linear/search-normal.svg" class="search-icon" alt="search">
   </div>

   <div class="menu-section">
       <div class="section-title">Recent Searches</div>
       <div class="search-item">
           <div class="search-item-icon">
               <img src="sources/icons/bulk/clock-counter-clockwise.svg" width="16" height="16" alt="recent">
           </div>
           <div class="search-item-content">
               <div class="search-item-title">Project</div>
               <div class="search-item-subtitle">2 hour ago</div>
           </div>
       </div>
   </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function () {
   const searchContainer = document.querySelector('.search-container');
   const searchInput = document.querySelector('.search-input');
   let isOpen = false;

   function openSearch() {
       if (!isOpen) {
           searchInput.value = '';
           searchContainer.style.display = 'block';
           gsap.set(searchContainer, {
               y: -20,
               opacity: 0
           });

           const menuSection = document.querySelector('.menu-section');
           gsap.set(menuSection, {
               height: 0,
               opacity: 0,
               paddingTop: 0,
               paddingBottom: 0
           });

           gsap.set('.search-item', {
               y: 20,
               opacity: 0
           });

           // Animate container
           gsap.to(searchContainer, {
               y: 100,
               opacity: 1,
               duration: 0.4,
               ease: "power3.out"
           });

           // Animate menu
           gsap.to(menuSection, {
               height: "auto",
               opacity: 1,
               paddingTop: 12,
               paddingBottom: 12,
               duration: 0.5,
               delay: 0.2,
               ease: "power3.out"
           });

           // Animate items
           gsap.to('.search-item', {
               y: 0,
               opacity: 1,
               duration: 0.4,
               stagger: 0.1,
               delay: 0.4,
               ease: "power2.out",
               onComplete: () => {
                   searchInput.focus();
                   isOpen = true;
               }
           });
       }
   }

   function closeSearch() {
       if (isOpen) {
           const menuSection = document.querySelector('.menu-section');
           
           gsap.to(menuSection, {
               height: 0,
               opacity: 0,
               paddingTop: 0,
               paddingBottom: 0,
               duration: 0.4,
               ease: "power3.inOut",
               onComplete: () => {
                   gsap.to(searchContainer, {
                       y: 0,
                       opacity: 0,
                       duration: 0.3,
                       ease: "back.in(1.5)",
                       onComplete: () => {
                           searchContainer.style.display = 'none';
                           gsap.set(menuSection, {
                               clearProps: "all"
                           });
                           isOpen = false;
                       }
                   });
               }
           });
       }
   }

   document.addEventListener('keydown', (e) => {
       if (e.ctrlKey && !e.shiftKey && !e.altKey && e.key === 'Control' && e.location === 1) {
           e.preventDefault();
           if (!isOpen) {
               openSearch();
           } else {
               closeSearch();
           }
       }
   });

   document.addEventListener('click', (e) => {
       if (isOpen && e.target !== searchInput) {
           closeSearch();
       }
   });
});
</script>
</body>
</html>