<?php

namespace Processwire;

class PageUseIdAsName extends WireData implements Module, ConfigurableModule
{

    public static function getModuleInfo()
    {
        return array(
            'title' => 'PageUseIdAsName',
            'class' => 'PageUseIdAsName',
            'version' => 112,
            'summary' => 'Overrides the name field (used for url) with the page-id and hides the field from the admin GUI',
            'singular' => true,
            'autoload' => true,
            'requires' => [
                'PHP>=7.0.0',
                'ProcessWire>=3.0.148'
            ]
        );
    }

    public function init()
    {
        $this->pages->addHookAfter('added', $this, 'changePageName');
        $this->addHookAfter('AdminTheme::getExtraMarkup', $this, 'hideNameField');
    }

    public function hideNameField(HookEvent $event)
    {
        $parts = $event->return;
        $fieldName = 'page_use_id_as_name_templates';
        $allowedTemplates = $this->get($fieldName);

        if($allowedTemplates && !empty($allowedTemplates)) {
            $allowedTemplates = wire('templates')->find("id=" . implode('|', $allowedTemplates));
            if ($allowedTemplates && $allowedTemplates->count() > 0) {
                $cssSelectors = [];
                foreach ($allowedTemplates as $template) {
                    array_push($cssSelectors, ".ProcessPageEdit-template-$template->name .InputfieldPageName");
                }
                if(count($cssSelectors) > 0) {
                    $parts['head'] .= '<style type="text/css">' . implode(',', $cssSelectors) . ' { display: none; }</style>';
                }
            }
        }

        $event->return = $parts;
    }

    public function changePageName(HookEvent $event)
    {
        $page = $event->arguments[0];
        if ($this->isAllowedTemplate($page->template)) {
            $id = wire('sanitizer')->pageName($page->id);
            if (wire('languages')) {
                foreach (wire('languages') as $language) {
                    if ($language->isDefault()) {
                        $page->name = $id;
                    } else {
                        // activate language
                        $page->set("status$language->id", 1);
                        // set pageid
                        $page->set("name$language->id", $id);
                    }
                }
            } else {
                $page->name = $id;
            }
            $page->save([
                "quiet" => true,
				"noHooks" => true
            ]);
        }
    }

    public static function getModuleConfigInputfields(array $data)
    {
        $wrapper = new InputfieldWrapper();
        $fieldName = 'page_use_id_as_name_templates';

        $templates = [];
        foreach (wire('templates') as $template) {
            // dont add system templates
            if ($template->flags == Template::flagSystem) continue;
            $templates[$template->id] = $template->name;
        }

        $field = wire('modules')->get('InputfieldAsmSelect');
        $field->label = __('Templates');
        $field->description = __('Choose for which templates the rule should be applied.');
        $field->attr('name', $fieldName);
        $field->attr('value', isset($data[$fieldName]) ? $data[$fieldName] : null);
        $field->addOptions($templates);
        $wrapper->append($field);

        return $wrapper;
    }

    protected function isAllowedTemplate(Template $template)
    {
        $fieldName = 'page_use_id_as_name_templates';
        $allowedTemplates = $this->get($fieldName);
        return in_array($template->id, $allowedTemplates);
    }
}
