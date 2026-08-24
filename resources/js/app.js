// Register Alpine plugins on the Livewire-bundled Alpine instance.
// Importing 'alpinejs' standalone here would create a SECOND Alpine
// (Livewire 3 ships its own bundled copy), which silently breaks
// Livewire reactivity — clicks fire but state updates don't flow.
import collapse from '@alpinejs/collapse';
import intersect from '@alpinejs/intersect';

document.addEventListener('alpine:init', () => {
    window.Alpine.plugin(collapse);
    window.Alpine.plugin(intersect);
});
