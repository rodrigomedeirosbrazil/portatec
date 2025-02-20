@push('styles')
    <style>
        .slider-container {
            user-select: none;
            -webkit-user-select: none;
            -ms-user-select: none;
        }
    </style>
@endpush

<div
    wire:key="{{ $key }}"
    x-data="{
        isDragging: false,
        startX: 0,
        left: 0,
        maxLeft: 0,
        init() {
            this.maxLeft = this.$refs.container.clientWidth - this.$refs.slider.clientWidth;
        },
        startDrag(event) {
            this.isDragging = true;
            this.startX = event.touches ? event.touches[0].clientX : event.clientX;
        },
        onDrag(event) {
            if (!this.isDragging) return;
            let clientX = event.touches ? event.touches[0].clientX : event.clientX;
            this.left = Math.max(0, Math.min(this.maxLeft, clientX - this.startX));
        },
        stopDrag() {
            this.isDragging = false;
            if (this.left >= this.maxLeft) {
                $dispatch('unlock');
                this.left = 0;
            } else {
                this.left = 0;
            }
        }
    }" x-init="init" class="relative w-64 h-11 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center p-1.5 slider-container ring-1 ring-gray-950/5 dark:ring-white/10" x-ref="container">
    <div x-ref="slider" class="absolute left-0 w-11 h-11 bg-primary-600 dark:bg-primary-500 hover:bg-primary-500 dark:hover:bg-primary-400 rounded-full flex justify-center items-center cursor-pointer shadow-lg transition-all duration-200"
        x-bind:style="'left: ' + left + 'px'"
        @mousedown="startDrag"
        @touchstart="startDrag"
        @mousemove.window="onDrag"
        @touchmove.window="onDrag"
        @mouseup.window="stopDrag"
        @touchend.window="stopDrag">
        <svg class="w-5 h-5 text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
            <path fill-rule="evenodd" d="M4.5 5.653c0-1.426 1.529-2.33 2.779-1.643l11.54 6.348c1.295.712 1.295 2.573 0 3.285L7.28 19.991c-1.25.687-2.779-.217-2.779-1.643V5.653z" clip-rule="evenodd" />
        </svg>
    </div>
    <span class="w-full text-center text-sm font-medium text-gray-700 dark:text-gray-200">Swipe to Unlock</span>
</div>
