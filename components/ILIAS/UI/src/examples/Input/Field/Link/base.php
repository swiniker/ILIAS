<?php

declare(strict_types=1);

namespace ILIAS\UI\examples\Input\Field\Link;

/**
 * ---
 * description: >
 *   Example of rendering a link.
 *
 * expected output: >
 *   ILIAS shows two input fields titled "Label" and "URL". You can enter letters and numbers intothe label field. You
 *   can enter a valid URL into the URL field. Please insert a valid label and URL and save your input. This should not
 *   throw any errors.
 *   Now enter a text into the label field and an invalid URL into the URL field and save your input. An error message
 *   should be displayed.
 * ---
 */
function base()
{
    global $DIC;
    $ui = $DIC->ui()->factory();
    $renderer = $DIC->ui()->renderer();
    $request = $DIC->http()->request();

    $link_input = $ui->input()->field()->link("Link Input", "Enter a label and the url ")
        ->withValue(['ILIAS Homepage', "https://www.ilias.de/"]);

    $form = $ui->input()->container()->form()->standard("#", [$link_input]);

    $result = "No result yet.";
    if ($request->getMethod() == "POST") {
        $form = $form->withRequest($request);
        $data = $form->getData();
        if ($data) {
            $result = $data[0];
        }
    }

    return
        "<pre>" . print_r($result, true) . "</pre><br />" .
        $renderer->render($form);
}
