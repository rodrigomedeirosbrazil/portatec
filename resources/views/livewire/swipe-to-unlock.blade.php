@push('styles')
    <style>
        .slider-container {
            user-select: none;
            -webkit-user-select: none;
            -ms-user-select: none;
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('livewire:init', () => {
            const slider = document.getElementById("slider");
            const container = slider.parentElement;
            let isDragging = false;
            let startX, initialLeft;

            function startDrag(e) {
                isDragging = true;
                startX = e.touches ? e.touches[0].clientX : e.clientX;
                initialLeft = slider.offsetLeft;
            }

            function onDrag(e) {
                if (!isDragging) return;
                let clientX = e.touches ? e.touches[0].clientX : e.clientX;
                let newLeft = initialLeft + (clientX - startX);
                let maxLeft = container.clientWidth - slider.clientWidth;
                newLeft = Math.max(0, Math.min(maxLeft, newLeft));
                slider.style.left = newLeft + "px";
            }

            function stopDrag() {
                if (!isDragging) return;
                isDragging = false;
                if (slider.offsetLeft > container.clientWidth - slider.clientWidth - 5) {
                    Livewire.dispatch('unlock');
                    slider.style.left = "0px"; // Reset position after unlocking
                } else {
                    slider.style.left = "0px";
                }
            }

            slider.addEventListener("mousedown", startDrag);
            document.addEventListener("mousemove", onDrag);
            document.addEventListener("mouseup", stopDrag);

            slider.addEventListener("touchstart", startDrag);
            document.addEventListener("touchmove", onDrag);
            document.addEventListener("touchend", stopDrag);
        });
    </script>
@endpush

<div class="relative w-64 h-11 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center p-1.5 slider-container ring-1 ring-gray-950/5 dark:ring-white/10">
    <div id="slider" class="absolute left-0 w-11 h-11 bg-primary-600 dark:bg-primary-500 hover:bg-primary-500 dark:hover:bg-primary-400 rounded-full flex justify-center items-center cursor-pointer shadow-lg transition-all duration-200">
        <svg class="w-5 h-5 text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
            <path fill-rule="evenodd" d="M4.5 5.653c0-1.426 1.529-2.33 2.779-1.643l11.54 6.348c1.295.712 1.295 2.573 0 3.285L7.28 19.991c-1.25.687-2.779-.217-2.779-1.643V5.653z" clip-rule="evenodd" />
        </svg>
    </div>
    <span class="w-full text-center text-sm font-medium text-gray-700 dark:text-gray-200">Swipe to Unlock</span>
</div>
