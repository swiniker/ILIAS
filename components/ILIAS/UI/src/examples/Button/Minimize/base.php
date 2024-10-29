<?php

declare(strict_types=1);

namespace ILIAS\UI\examples\Button\Minimize;

/**
 * ---
 * description: >
 *   This example is rather artificial, since the minimize button is only used
 *   in other components (see purpose).
 *   This examples just shows how one could render the button if implementing
 *   such a component
 *
 * expected output: >
 *   ILIAS shows the rendered Component.
 *
 * note: >
 *  In some cases, additional CSS will be required for placing the button
 *  properly by the surrounding component.
 * ---
 */
function base()
{
    global $DIC;

    return $DIC->ui()->renderer()->render(
        $DIC->ui()->factory()->button()->minimize()
    );
}
