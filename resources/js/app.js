import registerCatalogPicker from './catalog-picker';

document.addEventListener('alpine:init', () => registerCatalogPicker(window.Alpine));
