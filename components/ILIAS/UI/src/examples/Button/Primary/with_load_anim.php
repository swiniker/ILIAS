<?php

declare(strict_types=1);

namespace ILIAS\UI\examples\Button\Primary;

/**
 * ---
 * description: >
 *   Example for rendering a primary button with a loading animation
 *
 * expected output: >
 *   ILIAS shows an active, very colorful button titled "Goto ILIAS". After clicking the button the text will change to
 *   "Working..." and the button's color will change to grey. During that process you cannot click the button. After a
 *   while the button will change to it's origin color and the text will be "Done".
 * ---
 */
function with_load_anim()
{
    global $DIC;
    $f = $DIC->ui()->factory();
    $renderer = $DIC->ui()->renderer();

    return $renderer->render(
        $f->button()->primary("Goto ILIAS", "")
            ->withLoadingAnimationOnClick(true)
            ->withOnLoadCode(function ($id) {
                return
                    "$('#$id').click(function(e) {
							$('#$id').html('Working...');
							setInterval(
								function(){
									$('#$id').html('Done');
									il.UI.button.deactivateLoadingAnimation('$id');
								}
							,3000);
					});";
            })
    );
}
