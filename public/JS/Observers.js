$(document).ready(function (){
    const observer = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.getAttribute("data-src");
                img.removeAttribute("data-src");
                observer.unobserve(img);
            }
        });
    }, {
        rootMargin: "0px 0px 10px 10px"
    });

    const observeLazyImages = () => {
        document.querySelectorAll("img[data-src]").forEach(img => observer.observe(img));
    };

    const mutationObserver = new MutationObserver(mutations => {
        mutations.forEach(mutation => {
            if (mutation.addedNodes.length) {
                observeLazyImages();
            }
        });
    });

    mutationObserver.observe(document.body, { childList: true, subtree: true });

    observeLazyImages();
})