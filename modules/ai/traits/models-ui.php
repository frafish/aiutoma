<?php
namespace Aiutoma\Modules\Ai\Traits;
if ( ! defined( 'ABSPATH' ) ) exit;
trait ModelsUi {
    public function aiutoma_models_page_html() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'aiutoma'));
        }
        
        $enabled_models = get_option('aiutoma_enabled_models', []);
        $cron_enabled = wp_next_scheduled('aiutoma_update_models_cron') !== false;

        $models_data = [];
        $providers = [];
        $families = [];
        $all_capabilities = [];

        if (class_exists('\WordPress\AiClient\AiClient')) {
            $registry = \WordPress\AiClient\AiClient::defaultRegistry();
            $requirements = new \WordPress\AiClient\Providers\Models\DTO\ModelRequirements([], []);
            $providerModels = $registry->findModelsMetadataForSupport($requirements);
            
            foreach ($providerModels as $providerMetadata) {
                $providerName = $providerMetadata->getProvider()->getName();
                $providerId = $providerMetadata->getProvider()->getId();
                if (!in_array($providerName, $providers)) {
                    $providers[] = $providerName;
                }
                
                foreach ($providerMetadata->getModels() as $modelMeta) {
                    $id = $modelMeta->getId();
                    $name = $modelMeta->getName() ?: $id;
                    $uid = $providerId . '|' . $id;
                    
                    $family = 'Other';
                    if (stripos($name, 'claude') !== false) {
                        $family = 'Claude';
                    } elseif (stripos($name, 'gpt') !== false || stripos($name, 'o1') !== false || stripos($name, 'openai') !== false) {
                        $family = 'GPT';
                    } elseif (stripos($name, 'gemini') !== false || stripos($name, 'google') !== false) {
                        $family = 'Gemini / Google';
                    } elseif (stripos($name, 'gemma') !== false) {
                        $family = 'Gemma';
                    } elseif (stripos($name, 'llama') !== false) {
                        $family = 'Llama';
                    } elseif (stripos($name, 'mistral') !== false || stripos($name, 'mixtral') !== false || stripos($name, 'codestral') !== false || stripos($name, 'voxtral') !== false || stripos($name, 'devstral') !== false || stripos($name, 'pixtral') !== false || stripos($name, 'mathstral') !== false || stripos($name, 'magistral') !== false || stripos($name, 'ministral') !== false) {
                        $family = 'Mistral';
                    } elseif (stripos($name, 'qwen') !== false) {
                        $family = 'Qwen';
                    } elseif (stripos($name, 'glm') !== false) {
                        $family = 'GLM';
                    } elseif (stripos($name, 'deepseek') !== false) {
                        $family = 'DeepSeek';
                    } elseif (stripos($name, 'nano banana') !== false || stripos($name, 'banana') !== false) {
                        $family = 'Nano Banana';
                    } elseif (stripos($name, 'phi') !== false) {
                        $family = 'Phi';
                    } elseif (stripos($name, 'perplexity') !== false || stripos($name, 'sonar') !== false) {
                        $family = 'Perplexity';
                    } elseif (stripos($name, 'cohere') !== false || stripos($name, 'command') !== false) {
                        $family = 'Cohere';
                    } elseif (stripos($name, 'veo') !== false) {
                        $family = 'Veo';
                    } elseif (stripos($name, 'nova') !== false) {
                        $family = 'Amazon Nova';
                    } elseif (stripos($name, 'nemotron') !== false) {
                        $family = 'Nemotron';
                    } elseif (stripos($name, 'lyria') !== false) {
                        $family = 'Lyria';
                    } elseif (stripos($name, 'moonshotai') !== false || stripos($name, 'kimi') !== false) {
                        $family = 'Kimi';
                    } elseif (stripos($name, 'minimax') !== false) {
                        $family = 'MiniMax';
                    } elseif (stripos($name, 'tencent') !== false || stripos($name, 'hunyuan') !== false || stripos($name, 'hy3') !== false) {
                        $family = 'Hunyuan';
                    } elseif (stripos($name, 'stepfun') !== false || stripos($name, 'step-') !== false) {
                        $family = 'StepFun';
                    } elseif (stripos($name, 'xiaomi') !== false || stripos($name, 'mimo') !== false) {
                        $family = 'MiMo';
                    } elseif (stripos($name, 'allenai') !== false || stripos($name, 'olmo') !== false) {
                        $family = 'OlMo';
                    } elseif (stripos($name, 'wizardlm') !== false) {
                        $family = 'WizardLM';
                    } elseif (stripos($name, 'cogito') !== false) {
                        $family = 'Cogito';
                    } elseif (stripos($name, 'baidu') !== false || stripos($name, 'ernie') !== false) {
                        $family = 'ERNIE';
                    } elseif (stripos($name, 'grok') !== false || stripos($name, 'x-ai') !== false) {
                        $family = 'Grok';
                    } elseif (stripos($name, 'aion-labs') !== false || stripos($name, 'aion') !== false) {
                        $family = 'Aion';
                    } elseif (stripos($name, 'laguna') !== false) {
                        $family = 'Laguna';
                    } elseif (stripos($name, 'reka') !== false) {
                        $family = 'Reka';
                    } elseif (stripos($name, 'hermes') !== false) {
                        $family = 'Hermes';
                    } elseif (stripos($name, 'jamba') !== false) {
                        $family = 'Jamba';
                    } elseif (stripos($name, 'imagen') !== false) {
                        $family = 'Imagen';
                    } elseif (stripos($name, 'antigravity') !== false) {
                        $family = 'Antigravity';
                    } elseif (stripos($name, 'deep research') !== false || stripos($name, 'deep-research') !== false) {
                        $family = 'Deep Research';
                    } elseif (stripos($name, 'flux') !== false) {
                        $family = 'Flux';
                    } elseif (stripos($name, 'stable-diffusion') !== false || stripos($name, 'stable diffusion') !== false || stripos($name, 'sdxl') !== false) {
                        $family = 'Stable Diffusion';
                    } elseif (stripos($name, 'bge') !== false) {
                        $family = 'BGE';
                    } elseif (stripos($name, 'granite') !== false) {
                        $family = 'Granite';
                    } elseif (stripos($name, 'seed') !== false) {
                        $family = 'Seed';
                    } elseif (stripos($name, 'solar') !== false) {
                        $family = 'Solar';
                    } elseif (stripos($name, 'leonardo') !== false || stripos($name, 'phoenix') !== false || stripos($name, 'lucid') !== false) {
                        $family = 'Leonardo';
                    } elseif (stripos($name, 'aura') !== false) {
                        $family = 'Aura';
                    } elseif (stripos($name, 'llava') !== false) {
                        $family = 'LLaVA';
                    }
                    if (!in_array($family, $families)) {
                        $families[] = $family;
                    }
                    
                    $capabilities = [];
                    foreach ($modelMeta->getSupportedCapabilities() as $cap) {
                        $capabilities[] = $cap->value;
                        if (!in_array($cap->value, $all_capabilities)) {
                            $all_capabilities[] = $cap->value;
                        }
                    }
                    
                    if ($family === 'Veo' && !in_array('video_generation', $capabilities)) {
                        $capabilities[] = 'video_generation';
                        if (!in_array('video_generation', $all_capabilities)) {
                            $all_capabilities[] = 'video_generation';
                        }
                    }
                    if ($family === 'Lyria' && !in_array('music_generation', $capabilities)) {
                        $capabilities[] = 'music_generation';
                        if (!in_array('music_generation', $all_capabilities)) {
                            $all_capabilities[] = 'music_generation';
                        }
                    }

                    $models_data[] = [
                        'uid' => $uid,
                        'id' => $id,
                        'name' => $name,
                        'provider' => $providerName,
                        'family' => $family,
                        'capabilities' => $capabilities,
                        'is_enabled' => empty($enabled_models) || in_array($uid, $enabled_models)
                    ];
                }
            }
        }
        
        $models_data = apply_filters('aiutoma/models', $models_data);
        
        foreach ($models_data as $m) {
            if (!in_array($m['provider'], $providers)) $providers[] = $m['provider'];
            if (!in_array($m['family'], $families)) $families[] = $m['family'];
            foreach ($m['capabilities'] as $cap) {
                if (!in_array($cap, $all_capabilities)) $all_capabilities[] = $cap;
            }
        }
        
        sort($providers);
        sort($families);
        sort($all_capabilities);
        
        $capability_labels = [
            'text_generation' => 'Text Generation',
            'image_generation' => 'Image Generation',
            'text_to_speech_conversion' => 'Text to Speech',
            'speech_generation' => 'Speech Generation',
            'music_generation' => 'Music Generation',
            'video_generation' => 'Video Generation',
            'embedding_generation' => 'Embeddings',
            'chat_history' => 'Chat History'
        ];
        ?>
        <div class="wrap">
            <h1><span class="dashicons dashicons-admin-network"></span> <?php esc_html_e('AI Models', 'aiutoma'); ?></h1>
            <p><?php esc_html_e('Manage available AI models, filter them, and configure which ones should be enabled across the platform.', 'aiutoma'); ?></p>

            <div style="background: #fff; padding: 15px; border: 1px solid #ccc; margin-bottom: 20px; display: flex; gap: 20px; align-items: center;">
                <label>
                    <input type="checkbox" id="aiutoma-cron-enabled" <?php checked(!empty($cron_enabled)); ?>>
                    <?php esc_html_e('Enable automatic daily update of models', 'aiutoma'); ?>
                </label>

                <button id="aiutoma-trigger-update" class="button button-secondary"><?php esc_html_e('Force Update Models Now', 'aiutoma'); ?></button>
                <button id="aiutoma-save-settings" class="button button-primary"><?php esc_html_e('Save Settings', 'aiutoma'); ?></button>
                <span id="aiutoma-settings-spinner" class="spinner"></span>
            </div>

            <div style="margin-bottom: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                <input type="text" id="aiutoma-models-search" placeholder="<?php esc_attr_e('Search models by name...', 'aiutoma'); ?>" style="width: 100%; max-width: 300px; padding: 6px 12px;">
                
                <div id="aiutoma-provider-filter" class="aiutoma-dropdown-check-list">
                    <span class="anchor"><?php esc_html_e('Select Providers', 'aiutoma'); ?></span>
                    <ul class="items">
                        <?php foreach ($providers as $prov) : ?>
                            <li><label><input type="checkbox" value="<?php echo esc_attr(strtolower($prov)); ?>" /> <span class="label-text" data-label="<?php echo esc_attr($prov); ?>"><?php echo esc_html($prov); ?></span></label></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div id="aiutoma-family-filter" class="aiutoma-dropdown-check-list">
                    <span class="anchor"><?php esc_html_e('Select Families', 'aiutoma'); ?></span>
                    <ul class="items">
                        <?php foreach ($families as $fam) : ?>
                            <li><label><input type="checkbox" value="<?php echo esc_attr(strtolower($fam)); ?>" /> <span class="label-text" data-label="<?php echo esc_attr($fam); ?>"><?php echo esc_html($fam); ?></span></label></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div id="aiutoma-capability-filter" class="aiutoma-dropdown-check-list">
                    <span class="anchor"><?php esc_html_e('Select Capabilities', 'aiutoma'); ?></span>
                    <ul class="items">
                        <?php foreach ($all_capabilities as $cap) : ?>
                            <?php $label = $capability_labels[$cap] ?? ucfirst(str_replace('_', ' ', $cap)); ?>
                            <li><label><input type="checkbox" value="<?php echo esc_attr(strtolower($cap)); ?>" /> <span class="label-text" data-label="<?php echo esc_attr($label); ?>"><?php echo esc_html($label); ?></span></label></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <select id="aiutoma-status-filter" style="padding: 6px 12px;">
                    <option value="" data-label="<?php esc_attr_e('All Statuses', 'aiutoma'); ?>"><?php esc_html_e('All Statuses', 'aiutoma'); ?></option>
                    <option value="1" data-label="<?php esc_attr_e('Enabled', 'aiutoma'); ?>"><?php esc_html_e('Enabled', 'aiutoma'); ?></option>
                    <option value="0" data-label="<?php esc_attr_e('Disabled', 'aiutoma'); ?>"><?php esc_html_e('Disabled', 'aiutoma'); ?></option>
                </select>
            </div>
            
            <p style="margin-bottom: 10px;"><strong><?php esc_html_e('Total Models:', 'aiutoma'); ?></strong> <span id="aiutoma-total-models-count"><?php echo esc_html(count($models_data)); ?></span> / <?php echo esc_html(count($models_data)); ?></p>
            
            <table class="wp-list-table widefat fixed striped" id="aiutoma-models-table">
                <thead>
                    <tr>
                        <th style="width: 5%;"><input type="checkbox" id="aiutoma-models-select-all" checked></th>
                        <th style="width: 25%;"><?php esc_html_e('Name', 'aiutoma'); ?></th>
                        <th style="width: 15%;"><?php esc_html_e('Provider', 'aiutoma'); ?></th>
                        <th style="width: 15%;"><?php esc_html_e('Family', 'aiutoma'); ?></th>
                        <th style="width: 40%;"><?php esc_html_e('Capabilities', 'aiutoma'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($models_data)) : ?>
                        <tr><td colspan="5"><?php esc_html_e('No models found.', 'aiutoma'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($models_data as $m) : ?>
                            <tr class="aiutoma-model-row">
                                <td>
                                    <input type="checkbox" class="aiutoma-model-checkbox" value="<?php echo esc_attr($m['uid']); ?>" <?php checked(!empty($m['is_enabled'])); ?>>
                                </td>
                                <td class="aiutoma-model-name" data-search="<?php echo esc_attr(strtolower($m['name'] . ' ' . $m['id'])); ?>">
                                    <strong><?php echo esc_html($m['name']); ?></strong><br>
                                    <small style="color:#666;"><?php echo esc_html($m['id']); ?></small>
                                </td>
                                <td class="aiutoma-model-provider" data-filter="<?php echo esc_attr(strtolower($m['provider'])); ?>">
                                    <?php echo esc_html($m['provider']); ?>
                                </td>
                                <td class="aiutoma-model-family" data-filter="<?php echo esc_attr(strtolower($m['family'])); ?>">
                                    <?php echo esc_html($m['family']); ?>
                                </td>
                                <td class="aiutoma-model-capabilities" data-filter="<?php echo esc_attr(strtolower(implode(',', $m['capabilities']))); ?>">
                                    <small><?php echo esc_html(implode(', ', $m['capabilities'])); ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
