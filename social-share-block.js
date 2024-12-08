(function(blocks, element) {
    const { createElement } = element;
    const { registerBlockType } = blocks;

    registerBlockType('social-share/block', {
        title: 'Social Share Buttons',
        icon: 'share',
        category: 'common',
        edit: function(props) {
            return createElement('div', { className: 'social-share-block' }, 'Social Share Buttons (Frontend Preview Only)');
        },
        save: function() {
            return null; // Rendered via PHP
        }
    });
})(window.wp.blocks, window.wp.element);
