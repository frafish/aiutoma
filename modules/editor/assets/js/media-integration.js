(function(wp) {
    const { addFilter } = wp.hooks;
    const { createHigherOrderComponent } = wp.compose;
    const { Fragment, createElement } = wp.element;
    const { Button, MenuItem } = wp.components;
    const { select, dispatch } = wp.data;

    const TRIGGER_AGENT = (defaultPrompt = "") => {
        const $chatbot = jQuery('#aiutoma-agent-chatbot');
        const $toggleBtn = jQuery('#aiutoma-agent-toggle span');
        const $prompt = jQuery('#aiutoma-agent-prompt');
        
        if ($chatbot.length && $prompt.length) {
            if ($chatbot.hasClass('aiutoma-agent-closed')) {
                $toggleBtn.click();
            }
            $prompt.val('Please use your image generation ability to generate an image and then update the current block with it. My prompt is: ' + defaultPrompt);
            $prompt.focus();
        } else {
            alert('Aiutoma Agent is not active or could not be found.');
        }
    };

    // 1. Hook into MediaPlaceholder (empty image block)
    const withAiutomaMediaPlaceholder = createHigherOrderComponent((OriginalComponent) => {
        return (props) => {
            const { getSelectedBlock } = select('core/block-editor');
            const selectedBlock = getSelectedBlock();
            
            // Only show for supported blocks
            const supportedBlocks = ['core/image', 'core/cover', 'core/media-text'];
            if (!selectedBlock || !supportedBlocks.includes(selectedBlock.name)) {
                return createElement(OriginalComponent, props);
            }

            const aiutomaButton = createElement(
                Button,
                {
                    variant: 'secondary',
                    className: 'aiutoma-generate-media-btn',
                    style: {
                        marginTop: '10px',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        width: '100%',
                        background: 'linear-gradient(135deg, #6366f1, #a855f7)',
                        color: 'white',
                        border: 'none'
                    },
                    onClick: () => TRIGGER_AGENT()
                },
                [
                    createElement('span', { className: 'dashicons dashicons-superhero', style: { marginRight: '5px' }, key: 'icon' }),
                    'Generate with Aiutoma'
                ]
            );

            // Inject the button into the placeholder
            if (props.placeholder) {
                const originalPlaceholder = props.placeholder;
                props.placeholder = (placeholderProps) => {
                    const originalRender = originalPlaceholder(placeholderProps);
                    return createElement(Fragment, null, [originalRender, aiutomaButton]);
                };
            }

            return createElement(Fragment, null, [
                createElement(OriginalComponent, props),
                (!props.placeholder) ? aiutomaButton : null
            ]);
        };
    }, 'withAiutomaMediaPlaceholder');

    addFilter(
        'editor.MediaPlaceholder',
        'aiutoma/media-placeholder-integration',
        withAiutomaMediaPlaceholder
    );

    // 2. Hook into MediaReplaceFlow (dropdown for existing image)
    const withAiutomaMediaReplaceFlow = createHigherOrderComponent((OriginalComponent) => {
        return (props) => {
            const { getSelectedBlock } = select('core/block-editor');
            const selectedBlock = getSelectedBlock();
            
            const supportedBlocks = ['core/image', 'core/cover', 'core/media-text'];
            if (!selectedBlock || !supportedBlocks.includes(selectedBlock.name)) {
                return createElement(OriginalComponent, props);
            }

            const { children, ...restProps } = props;

            return createElement(Fragment, null, [
                createElement(OriginalComponent, {
                    ...restProps,
                    children: (innerProps) => {
                        return createElement(Fragment, null, [
                            createElement(MenuItem, {
                                icon: 'superhero',
                                onClick: () => {
                                    if (innerProps && innerProps.onClose) {
                                        innerProps.onClose();
                                    }
                                    TRIGGER_AGENT();
                                }
                            }, 'Generate with Aiutoma'),
                            (typeof children === 'function') ? children(innerProps) : children
                        ]);
                    }
                })
            ]);
        };
    }, 'withAiutomaMediaReplaceFlow');

    addFilter(
        'editor.MediaReplaceFlow',
        'aiutoma/media-replace-flow-integration',
        withAiutomaMediaReplaceFlow
    );

})(window.wp);
