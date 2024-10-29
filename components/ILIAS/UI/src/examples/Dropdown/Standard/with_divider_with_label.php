<?php

declare(strict_types=1);

namespace ILIAS\UI\examples\Dropdown\Standard;

/**
 * ---
 * description: >
 *   Example for rendering a dropdown with a divider including a label
 *
 * expected output: >
 *   ILIAS shows a button titled "Actions" and a triangle pointing to the bottom. Clicking the button will open a
 *   dropdown menu with the entries "GitHub", "Docu", "Features" and "Bugs" rendered as shy buttons. The last three
 *   entries are positioned under the not clickable caption "ILIAS". Clicking the entries will open the appropriate
 *   website in the same browser window.
 * ---
 */
function with_divider_with_label()
{
    global $DIC;
    $f = $DIC->ui()->factory();
    $renderer = $DIC->ui()->renderer();

    $items = array(
        $f->button()->shy("GitHub", "https://www.github.com"),
        $f->divider()->horizontal()->withLabel("ILIAS"),
        $f->button()->shy("Docu", "https://www.ilias.de"),
        $f->button()->shy("Features", "https://feature.ilias.de"),
        $f->button()->shy("Bugs", "https://mantis.ilias.de"),
    );
    return $renderer->render($f->dropdown()->standard($items)->withLabel("Actions"));
}
