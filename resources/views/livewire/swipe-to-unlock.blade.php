<div wire:ignore x-data="{
        unlocked: @entangle('unlocked'),
        startX: 0,
        isDragging: false,
        moveSlider(e) {
            if (!this.isDragging) return;
            let clientX = e.touches ? e.touches[0].clientX : e.clientX;
            let newLeft = clientX - this.startX;
            let maxLeft = $refs.container.clientWidth - $refs.slider.clientWidth;
            newLeft = Math.max(0, Math.min(maxLeft, newLeft));
            $refs.slider.style.left = newLeft + 'px';
            if (newLeft >= maxLeft - 5) {
                this.unlocked = true;
                $wire.unlock();
            }
        },
        stopDrag() {
            this.isDragging = false;
            if (!this.unlocked) {
                $refs.slider.style.left = '0px';
            }
        }
    }"
    class="relative w-64 h-12 bg-gray-700 rounded-full flex items-center p-1 shadow-md">
    <div x-ref="slider" x-on:mousedown="isDragging = true; startX = $event.clientX" x-on:mousemove.window="moveSlider" x-on:mouseup.window="stopDrag" class="absolute left-0 w-12 h-10 bg-primary-500 rounded-full flex justify-center items-center cursor-pointer transition-all">
        <span class="text-white font-bold">▶</span>
    </div>
    <span class="w-full text-center text-white font-semibold" x-show="!unlocked">Swipe to Unlock</span>
    <span class="w-full text-center text-green-400 font-semibold" x-show="unlocked">✅ Unlocked!</span>
</div>
