<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 */

/**
 * Group Import Parser
 *
 * @author Stefan Meyer <meyer@leifos.com>
 */
class ilCategoryXmlParser extends ilSaxParser
{
    const MODE_CREATE = 1;
    const MODE_UPDATE = 2;

    private ?ilObjCategory $cat = null;
    private int $parent_id = 0;
    private array $current_translation = array();
    private string $current_container_setting;
    protected ilLogger $cat_log;
    protected int $mode;
    protected string $cdata = "";

    public function __construct(string $a_xml, int $a_parent_id)
    {
        parent::__construct(null);

        $this->mode = ilCategoryXmlParser::MODE_CREATE;
        $this->parent_id = $a_parent_id;
        $this->setXMLContent($a_xml);

        $this->cat_log = ilLoggerFactory::getLogger("cat");
    }
    
    public function getParentId() : int
    {
        return $this->parent_id;
    }
    
    protected function getCurrentTranslation() : array
    {
        return $this->current_translation;
    }
    
    public function setHandlers($a_xml_parser)
    {
        xml_set_object($a_xml_parser, $this);
        xml_set_element_handler($a_xml_parser, 'handlerBeginTag', 'handlerEndTag');
        xml_set_character_data_handler($a_xml_parser, 'handlerCharacterData');
    }

    public function startParsing()
    {
        parent::startParsing();

        if ($this->mode == ilCategoryXmlParser::MODE_CREATE) {
            return is_object($this->cat) ? $this->cat->getRefId() : false;
        } else {
            return is_object($this->cat) ? $this->cat->update() : false;
        }
    }

    public function handlerBeginTag($a_xml_parser, $a_name, $a_attribs)
    {
        switch ($a_name) {
            case "Category":
                break;
            
            case 'Translations':
                $this->getCategory()->removeTranslations();
                break;
            
            case 'Translation':
                $this->current_translation = array();
                $this->current_translation['default'] = $a_attribs['default'] ? 1 : 0;
                $this->current_translation['lang'] = $a_attribs['language'];
                break;

            case 'Sorting':
            case 'Sort':
                ilContainerSortingSettings::_importContainerSortingSettings($a_attribs, $this->getCategory()->getId());
                break;
            
            case 'ContainerSetting':
                $this->current_container_setting = $a_attribs['id'];
                break;
        }
    }

    public function handlerEndTag($a_xml_parser, $a_name)
    {
        switch ($a_name) {
            case "Category":
                $this->save();
                break;
            
            case 'Title':
                $this->current_translation['title'] = trim($this->cdata);
                
                if ($this->current_translation['default']) {
                    $this->getCategory()->setTitle(trim($this->cdata));
                }
                
                break;
            
            case 'Description':
                $this->current_translation['description'] = trim($this->cdata);
                
                if ($this->current_translation['default']) {
                    $this->getCategory()->setDescription(trim($this->cdata));
                }
                
                break;
            
            case 'Translation':
                // Add translation
                $this->getCategory()->addTranslation(
                    (string) $this->current_translation['title'],
                    (string) $this->current_translation['description'],
                    (string) $this->current_translation['lang'],
                    (int) $this->current_translation['default']
                );
                break;
            
            case 'ContainerSetting':
                if ($this->current_container_setting) {
                    $this->cat_log->debug("Write container Setting, ID: " . $this->getCategory()->getId() . ", setting: " .
                        $this->current_container_setting . ", data: " . $this->cdata);
                    ilContainer::_writeContainerSetting(
                        $this->getCategory()->getId(),
                        $this->current_container_setting,
                        $this->cdata
                    );
                }
                break;

            case 'ContainerSettings':
                $this->cat->readContainerSettings();	// read container settings to member vars (call getter/setter), see #0019870
                break;
        }
        $this->cdata = '';
    }

    public function handlerCharacterData($a_xml_parser, $a_data)
    {
        #$a_data = str_replace("<","&lt;",$a_data);
        #$a_data = str_replace(">","&gt;",$a_data);

        if (!empty($a_data)) {
            $this->cdata .= $a_data;
        }
    }

    protected function save() : bool
    {

        /**
         * mode can be create or update
         */
        if ($this->mode == ilCategoryXmlParser::MODE_CREATE) {
            $this->create();
            $this->getCategory()->create();
            $this->getCategory()->createReference();
            $this->getCategory()->putInTree($this->getParentId());
            $this->getCategory()->setPermissions($this->getParentId());
        }
        $this->getCategory()->update();
        return true;
    }

    public function setMode(int $mode) : void
    {
        $this->mode = $mode;
    }

    public function setCategory(ilObjCategory $cat)
    {
        $this->cat = $cat;
    }
    
    public function getCategory() : ilObjCategory
    {
        return $this->cat;
    }
}
