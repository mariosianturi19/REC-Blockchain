<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peta Pembangkit EBT - Renewa</title>
    <meta name="description" content="Peta interaktif pembangkit Energi Baru Terbarukan di Indonesia">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    @vite('resources/css/app.css')
    
    <!-- AOS CSS -->
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* Custom smooth scrolling */
        html {
            scroll-behavior: smooth;
        }
        
        /* Custom animations untuk elemen yang belum terlihat */
        [data-aos] {
            pointer-events: none;
        }
        
        [data-aos].aos-animate {
            pointer-events: auto;
        }
        
        /* Navbar scroll effect */
        .navbar-scrolled {
            backdrop-filter: blur(10px);
            background-color: rgba(255, 255, 255, 0.9);
            transition: all 0.3s ease;
        }
        
        /* Modern gradient backgrounds */
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .hero-gradient {
            background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 25%, #f0f9ff 75%, #eff6ff 100%);
        }
        
        /* Glass effect for modern look */
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
        }
        
        /* Enhanced map container */
        .map-container {
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 
                0 32px 64px rgba(0, 0, 0, 0.12),
                0 0 0 1px rgba(255, 255, 255, 0.05);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        
        .map-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(16, 185, 129, 0.05) 0%, rgba(34, 197, 94, 0.05) 100%);
            opacity: 0;
            transition: opacity 0.4s ease;
            z-index: 1;
            pointer-events: none;
        }
        
        .map-container:hover::before {
            opacity: 1;
        }
        
        .map-container:hover {
            transform: translateY(-8px) scale(1.01);
            box-shadow: 
                0 40px 80px rgba(0, 0, 0, 0.15),
                0 0 0 1px rgba(16, 185, 129, 0.1);
        }
        
        /* Map iframe styling */
        .map-iframe {
            border: none;
            border-radius: 24px;
            transition: all 0.3s ease;
        }
        
        /* Floating elements */
        .floating-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .floating-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 25px 50px rgba(16, 185, 129, 0.15);
        }
        
        /* Modern button styling */
        .modern-btn {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            border: none;
            border-radius: 16px;
            padding: 16px 32px;
            font-weight: 600;
            color: white;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .modern-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .modern-btn:hover::before {
            left: 100%;
        }
        
        .modern-btn:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 15px 35px rgba(16, 185, 129, 0.4);
        }
        
        /* Statistics cards */
        .stats-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.7) 100%);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            transition: all 0.3s ease;
        }
        
        .stats-card:hover {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.8) 100%);
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        /* Energy type icons with modern colors */
        .energy-icon-solar { color: #f59e0b; filter: drop-shadow(0 4px 8px rgba(245, 158, 11, 0.3)); }
        .energy-icon-wind { color: #06b6d4; filter: drop-shadow(0 4px 8px rgba(6, 182, 212, 0.3)); }
        .energy-icon-hydro { color: #3b82f6; filter: drop-shadow(0 4px 8px rgba(59, 130, 246, 0.3)); }
        .energy-icon-geo { color: #ef4444; filter: drop-shadow(0 4px 8px rgba(239, 68, 68, 0.3)); }
        .energy-icon-bio { color: #10b981; filter: drop-shadow(0 4px 8px rgba(16, 185, 129, 0.3)); }
        
        /* Pulse animation for interactive elements */
        .pulse-ring {
            animation: pulse-ring 2s infinite;
        }
        
        @keyframes pulse-ring {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.7; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        /* Loading shimmer effect */
        .loading-shimmer {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
        }
        
        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        
        /* Responsive map height */
        .map-height {
            height: 70vh;
            min-height: 600px;
        }
        
        @media (max-width: 768px) {
            .map-height {
                height: 60vh;
                min-height: 500px;
            }
        }
        
        /* Hero pattern overlay */
        .hero-pattern {
            background-image: 
                radial-gradient(circle at 20% 50%, rgba(16, 185, 129, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(34, 197, 94, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 80%, rgba(59, 130, 246, 0.1) 0%, transparent 50%);
        }
    </style>
</head>
<body class="bg-gray-50">
    @include('layouts.partials.navbar')

    <!-- Hero Section -->
    <section class="pt-24 pb-6 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center" data-aos="fade-up">
                <h1 class="text-5xl md:text-6xl font-bold text-gray-900 mb-6">
                    Peta <span class="text-green-600">Pembangkit EBT</span>
                </h1>
                <p class="text-xl md:text-2xl text-gray-600 max-w-4xl mx-auto mb-8" data-aos="fade-up" data-aos-delay="200">
                    Jelajahi lokasi pembangkit energi terbarukan di seluruh Indonesia. Klik marker untuk melihat detail kapasitas, teknologi, dan status operasional.
                </p>
            </div>
        </div>
    </section>


    <!-- Interactive Map Section -->
    <section id="map-section" class="pt-6 pb-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Map Container -->
            <div class="map-container glass-card" data-aos="zoom-in" data-aos-duration="1000">
                <div class="relative">
                    <!-- Map Loading Indicator -->
                    <div id="map-loading" class="absolute inset-0 flex items-center justify-center bg-gray-100 rounded-3xl z-10">
                        <div class="text-center">
                            <div class="loading-shimmer w-16 h-16 rounded-full mx-auto mb-4"></div>
                            <p class="text-gray-600 font-medium">Memuat peta pembangkit...</p>
                        </div>
                    </div>
                    
                    <!-- Google Maps Embed -->
                    <iframe 
                        id="map-iframe"
                        src="https://www.google.com/maps/d/embed?mid=1cG4o5P8LSzr-ZB1WL2oK9rV8Ic7I77g&ehbc=2E312F&noprof=1" 
                        width="100%" 
                        class="map-iframe map-height w-full"
                        allowfullscreen="" 
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        onload="hideMapLoading()">
                    </iframe>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 bg-white relative overflow-hidden">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
        <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6" data-aos="fade-up">
            Siap Berkontribusi untuk <span class="text-green-600">Energi Bersih</span>?
        </h2>
        <p class="text-xl text-gray-700 mb-10 max-w-2xl mx-auto" data-aos="fade-up" data-aos-delay="200">
            Bergabunglah dengan gerakan energi bersih Indonesia. Beli REC dan dukung pengembangan energi terbarukan.
        </p>
        
        <div class="flex flex-col sm:flex-row gap-6 justify-center items-center" data-aos="fade-up" data-aos-delay="400">
            @auth
                <a href="{{ route('buyer.categoryselect') }}" class="modern-btn flex items-center space-x-3">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Beli REC Sekarang</span>
                </a>
            @else
                <a href="{{ route('login') }}" class="modern-btn flex items-center space-x-3">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Beli REC Sekarang</span>
                </a>
            @endauth
        </div>
    </div>
</section>


    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-12">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between">
                <div class="mb-8 md:mb-0">
                    <div class="flex items-center mb-4">
                        <svg class="h-6 w-6 text-green-400 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                        </svg>
                        <span class="font-bold text-xl">Renewa</span>
                    </div>
                    <p class="text-gray-400 mb-4">Hak Cipta © 2025 Renewa Indonesia.<br>Seluruh hak dilindungi undang-undang.</p>
                    <div class="flex space-x-4">
                       <a href="#" class="text-gray-400 hover:text-white transition-colors hover:scale-110"><svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/></svg></a>
                       <a href="#" class="text-gray-400 hover:text-white transition-colors hover:scale-110"><svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg></a>
                       <a href="#" class="text-gray-400 hover:text-white transition-colors hover:scale-110"><svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg></a>
                   </div>
               </div>

               <div class="grid grid-cols-2 md:grid-cols-3 gap-8">
                   <div>
                       <h3 class="text-lg font-semibold mb-4">Perusahaan</h3>
                       <ul class="space-y-2">
                           <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Tentang Kami</a></li>
                           <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Blog</a></li>
                           <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Hubungi Kami</a></li>
                           <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Harga</a></li>
                           <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Testimonial</a></li>
                       </ul>
                   </div>
                   <div>
                       <h3 class="text-lg font-semibold mb-4">Dukungan</h3>
                       <ul class="space-y-2">
                           <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Pusat Bantuan</a></li>
                           <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Syarat Layanan</a></li>
                           <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Legal</a></li>
                           <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Kebijakan Privasi</a></li>
                           <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Status</a></li>
                       </ul>
                   </div>
                   <div class="col-span-2 md:col-span-1">
                       <h3 class="text-lg font-semibold mb-4">Tetap terhubung</h3>
                       <p class="text-gray-400 mb-4">Dapatkan update dan berita terbaru tentang energi terbarukan</p>
                       <div class="flex">
                           <input type="email" placeholder="Email anda" class="px-4 py-2 w-full rounded-l-lg focus:outline-none text-gray-800 transition-all focus:ring-2 focus:ring-green-500">
                           <button class="bg-green-500 hover:bg-green-600 px-4 py-2 rounded-r-lg transition-all hover:scale-105">
                               <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                               </svg>
                           </button>
                       </div>
                   </div>
               </div>
           </div>
       </div>
   </footer>

    <!-- Scroll to Top Button -->
    <button id="scrollToTop" class="fixed bottom-8 right-8 bg-gradient-to-r from-green-500 to-green-600 text-white p-4 rounded-full shadow-lg hover:from-green-600 hover:to-green-700 transition-all duration-300 hover:scale-110 z-50 opacity-0 invisible">
        <i class="fas fa-chevron-up text-xl"></i>
    </button>

    <!-- AOS JavaScript -->
    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true,
            mirror: false,
            offset: 50,
            delay: 0,
        });

        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('navbar-scrolled');
            } else {
                navbar.classList.remove('navbar-scrolled');
            }
        });

        // Hide map loading indicator
        function hideMapLoading() {
            const loadingElement = document.getElementById('map-loading');
            if (loadingElement) {
                loadingElement.style.opacity = '0';
                setTimeout(() => {
                    loadingElement.style.display = 'none';
                }, 300);
            }
        }

        // Scroll to map function
        function scrollToMap() {
            document.getElementById('map-section').scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }

        // Scroll to top functionality
        const scrollToTopBtn = document.getElementById('scrollToTop');
        
        window.addEventListener('scroll', function() {
            if (window.scrollY > 500) {
                scrollToTopBtn.classList.remove('opacity-0', 'invisible');
                scrollToTopBtn.classList.add('opacity-100', 'visible');
            } else {
                scrollToTopBtn.classList.add('opacity-0', 'invisible');
                scrollToTopBtn.classList.remove('opacity-100', 'visible');
            }
        });

        scrollToTopBtn.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Enhanced hover effects for floating cards
        document.querySelectorAll('.floating-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-15px) scale(1.02)';
                this.style.boxShadow = '0 25px 50px rgba(16, 185, 129, 0.25)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
                this.style.boxShadow = '0 10px 25px rgba(0, 0, 0, 0.1)';
            });
        });

        // Add scroll progress indicator
        const scrollProgress = document.createElement('div');
        scrollProgress.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 0%;
            height: 4px;
            background: linear-gradient(90deg, #10b981, #3b82f6);
            z-index: 9999;
            transition: width 0.3s ease;
        `;
        document.body.appendChild(scrollProgress);

        window.addEventListener('scroll', function() {
            const scrollTop = window.pageYOffset;
            const docHeight = document.documentElement.scrollHeight - window.innerHeight;
            const scrollPercent = (scrollTop / docHeight) * 100;
            scrollProgress.style.width = scrollPercent + '%';
        });

        // Interactive map enhancements
        const mapContainer = document.querySelector('.map-container');
        if (mapContainer) {
            mapContainer.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-10px) scale(1.01)';
                this.style.boxShadow = '0 40px 80px rgba(0, 0, 0, 0.15)';
            });
            
            mapContainer.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
                this.style.boxShadow = '0 32px 64px rgba(0, 0, 0, 0.12)';
            });
        }

        // Counter animation for statistics
        function animateCounters() {
            const counters = document.querySelectorAll('.stats-card .text-lg');
            counters.forEach(counter => {
                const target = parseInt(counter.textContent.replace(/,/g, '').replace(' MW', ''));
                const duration = 2000;
                const step = target / (duration / 16);
                let current = 0;
                
                const timer = setInterval(() => {
                    current += step;
                    if (current >= target) {
                        counter.textContent = target.toLocaleString() + ' MW';
                        clearInterval(timer);
                    } else {
                        counter.textContent = Math.floor(current).toLocaleString() + ' MW';
                    }
                }, 16);
            });
        }

        // Trigger counter animation when stats are visible
        const statsObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounters();
                    statsObserver.unobserve(entry.target);
                }
            });
        });

        const statsSection = document.querySelector('.grid.grid-cols-2.md\\:grid-cols-5');
        if (statsSection) {
            statsObserver.observe(statsSection);
        }

        // Enhanced button interactions with ripple effect
        document.querySelectorAll('.modern-btn, button').forEach(button => {
            button.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.cssText = `
                    position: absolute;
                    width: ${size}px;
                    height: ${size}px;
                    left: ${x}px;
                    top: ${y}px;
                    background: rgba(255, 255, 255, 0.3);
                    border-radius: 50%;
                    transform: scale(0);
                    animation: ripple 0.6s linear;
                    pointer-events: none;
                `;
                
                this.style.position = 'relative';
                this.style.overflow = 'hidden';
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });

        // Add ripple animation keyframes
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(2);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Performance optimization: Lazy load images
        const images = document.querySelectorAll('img');
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.classList.remove('loading-shimmer');
                        observer.unobserve(img);
                    }
                }
            });
        });

        images.forEach(img => {
            if (img.dataset.src) {
                img.classList.add('loading-shimmer');
                imageObserver.observe(img);
            }
        });
    </script>
</body>
</html>