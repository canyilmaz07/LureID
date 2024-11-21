class HeroSlider {
    constructor() {
        this.slides = document.querySelectorAll('.slide');
        this.currentSlide = 0;
        this.progressBar = document.querySelector('.progress');
        this.slideDuration = 5;
        this.timeline = gsap.timeline();
        
        // Özel ease fonksiyonu oluştur
        this.customEase = CustomEase.create("custom", "M0,0 C0.05,0.05 0.1,0.1 0.15,0.15 C0.25,0.25 0.75,0.75 0.85,0.85 C0.9,0.9 0.95,0.95 1,1");
        
        this.init();
    }

    init() {
        // İlk slide'ı ayarla
        gsap.set(this.slides, {
            width: 0,
            autoAlpha: 0
        });
        gsap.set(this.slides[0], {
            width: "100%",
            autoAlpha: 1,
            className: "slide active"
        });
        
        // Progress bar'ı başlat
        this.startProgressAnimation();
        
        // Slideshow'u başlat
        this.startSlideshow();
    }

    startProgressAnimation() {
        // Progress bar'ı sıfırla
        gsap.set(this.progressBar, { width: 0 });
        
        // Progress bar animasyonu
        gsap.to(this.progressBar, {
            width: "100%",
            duration: this.slideDuration,
            ease: "custom",
            onComplete: () => {
                // Animasyon bittiğinde sıfırla
                gsap.set(this.progressBar, { width: 0 });
            }
        });
    }

    async moveToSlide(slideFrom, slideTo) {
        return new Promise(resolve => {
            const tl = gsap.timeline({
                onComplete: resolve
            });

            tl.to(slideFrom, {
                width: 0,
                autoAlpha: 0,
                duration: 1,
                ease: "power4.inOut",
                className: "slide"
            })
            .set(slideTo, {
                left: 'auto',
                right: 0
            }, 0)
            .to(slideTo, {
                width: "100%",
                autoAlpha: 1,
                duration: 1,
                ease: "power4.inOut",
                className: "slide active"
            }, 0)
            .set(slideTo, {
                left: 0,
                right: 'auto'
            });
        });
    }

    async nextSlide() {
        const slideFrom = this.slides[this.currentSlide];
        this.currentSlide = (this.currentSlide + 1) % this.slides.length;
        const slideTo = this.slides[this.currentSlide];

        // Progress bar'ı yeniden başlat
        this.startProgressAnimation();
        
        // Bir sonraki slide'a geç
        await this.moveToSlide(slideFrom, slideTo);
    }

    startSlideshow() {
        const runSlideshow = () => {
            setTimeout(() => {
                this.nextSlide().then(() => {
                    // Slideshow'u devam ettir
                    runSlideshow();
                });
            }, this.slideDuration * 1000);
        };

        // İlk progress bar animasyonunu başlat
        this.startProgressAnimation();

        // Slideshow'u başlat
        runSlideshow();
    }
}

// Custom ease fonksiyonu için yardımcı nokta hesaplama
function createCustomProgressEase() {
    // Başlangıç (yavaş)
    const p1 = {x: 0.1, y: 0.1};    // Kontrol noktası 1
    const p2 = {x: 0.2, y: 0.15};   // Kontrol noktası 2
    
    // Orta kısım (hızlı)
    const p3 = {x: 0.3, y: 0.4};    // Hızlanma başlangıcı
    const p4 = {x: 0.7, y: 0.6};    // Hızlanma bitişi
    
    // Bitiş (yavaş)
    const p5 = {x: 0.8, y: 0.85};   // Kontrol noktası 3
    const p6 = {x: 0.9, y: 0.9};    // Kontrol noktası 4
    
    return `M0,0 C${p1.x},${p1.y} ${p2.x},${p2.y} 0.3,0.3 C${p3.x},${p3.y} ${p4.x},${p4.y} 0.7,0.7 C${p5.x},${p5.y} ${p6.x},${p6.y} 1,1`;
}

// Head bölümüne GSAP CustomEase plugin'ini ekleyin
document.addEventListener('DOMContentLoaded', () => {
    // CustomEase plugin'ini yükle
    const script = document.createElement('script');
    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.0/CustomEase.min.js';
    script.onload = () => {
        // Plugin yüklendikten sonra slider'ı başlat
        CustomEase.create("custom", createCustomProgressEase());
        new HeroSlider();
    };
    document.head.appendChild(script);
});