<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

use ILIAS\HTTP\GlobalHttpState;
use ILIAS\Refinery\Factory as Refinery;

/**
 * Class ilAccountMail
 *
 * Sends e-mail to newly created accounts.
 *
 * @author Stefan Schneider <stefan.schneider@hrz.uni-giessen.de>
 * @author Alex Killing <alex.killing@hrz.uni-giessen.de>
 *
 */
class ilAccountMail
{
    private readonly GlobalHttpState $http;
    private readonly ilSetting $settings;
    private readonly Refinery $refinery;
    private readonly ilTree $repositoryTree;
    private readonly ilMailMimeSenderFactory $senderFactory;
    public string $u_password = '';
    public ?ilObjUser $user = null;
    public string $target = '';
    private bool $lang_variables_as_fallback = false;
    /** @var string[] */
    private array $attachments = [];
    private bool $attachConfiguredFiles = false;
    private array $amail = [];

    public function __construct()
    {
        global $DIC;
        $this->http = $DIC->http();
        $this->refinery = $DIC->refinery();
        $this->settings = $DIC->settings();
        $this->repositoryTree = $DIC->repositoryTree();
        $this->senderFactory = $DIC->mail()->mime()->senderFactory();
    }

    public function useLangVariablesAsFallback(bool $a_status): void
    {
        $this->lang_variables_as_fallback = $a_status;
    }

    public function areLangVariablesUsedAsFallback(): bool
    {
        return $this->lang_variables_as_fallback;
    }

    public function shouldAttachConfiguredFiles(): bool
    {
        return $this->attachConfiguredFiles;
    }

    public function setAttachConfiguredFiles(bool $attachConfiguredFiles): void
    {
        $this->attachConfiguredFiles = $attachConfiguredFiles;
    }

    public function setUserPassword(string $a_pwd): void
    {
        $this->u_password = $a_pwd;
    }

    public function getUserPassword(): string
    {
        return $this->u_password;
    }

    public function setUser(ilObjUser $a_user): void
    {
        if (
            $this->user instanceof ilObjUser &&
            $a_user->getId() !== $this->user->getId()
        ) {
            $this->attachments = [];
        }

        $this->user = $a_user;
    }

    public function getUser(): ?ilObjUser
    {
        return $this->user;
    }

    public function getTarget(): string
    {
        return $this->target;
    }

    public function reset(): void
    {
        $this->user = null;
        $this->u_password = '';
        $this->target = '';
    }

    /**
     * @param array{lang?: string, subject?: string, body?: string, sal_f?: string, sal_g?: string, sal_m?: string, type?: string, att_file?: string} $mailData
     * @return array{lang?: string, subject?: string, body?: string, sal_f?: string, sal_g?: string, sal_m?: string, type?: string, att_file?: string}
     */
    private function ensureValidMailDataShape(array $mailData): array
    {
        foreach (['lang', 'subject', 'body', 'sal_f', 'sal_g', 'sal_m', 'type'] as $key) {
            if (!isset($mailData[$key])) {
                $mailData[$key] = '';
            }
        }

        $mailData['subject'] = trim($mailData['subject']);
        $mailData['body'] = trim($mailData['body']);

        return $mailData;
    }

    /**
     * @return array{lang?: string, subject?: string, body?: string, sal_f?: string, sal_g?: string, sal_m?: string, type?: string}
     */
    private function readAccountMail(string $a_lang): array
    {
        if (!isset($this->amail[$a_lang]) || !is_array($this->amail[$a_lang])) {
            $this->amail[$a_lang] = $this->ensureValidMailDataShape(
                ilObjUserFolder::_lookupNewAccountMail($a_lang)
            );
        }

        return $this->amail[$a_lang];
    }

    /**
     * @param array{lang?: string, subject?: string, body?: string, sal_f?: string, sal_g?: string, sal_m?: string, type?: string, att_file?: string} $mailData
     * @throws \ILIAS\Filesystem\Exception\IOException
     */
    private function addAttachments(array $mailData): void
    {
        if (isset($mailData['att_file']) && $this->shouldAttachConfiguredFiles()) {
            $fs = new ilFSStorageUserFolder(USER_FOLDER_ID);
            $fs->create();

            $pathToFile = '/' . implode('/', array_map(static function (string $pathPart): string {
                return trim($pathPart, '/');
            }, [
                $fs->getAbsolutePath(),
                $mailData['lang'],
            ]));

            $this->addAttachment($pathToFile, $mailData['att_file']);
        }
    }

    /**
     * Sends the mail with its object properties as MimeMail
     * It first tries to read the mail body, subject and sender address from posted named formular fields.
     * If no field values found the defaults are used.
     * Placehoders will be replaced by the appropriate data.
     * @throws RuntimeException
     */
    public function send(): bool
    {
        $user = $this->getUser();
        if (!$user instanceof ilObjUser) {
            throw new RuntimeException('A user instance must be passed when sending emails');
        }

        if ($user->getEmail() === '') {
            return false;
        }

        // determine language and get account mail data
        // fall back to default language if acccount mail data is not given for user language.
        $amail = $this->readAccountMail($user->getLanguage());
        $lang = $user->getLanguage();
        if ($amail['body'] === '' || $amail['subject'] === '') {
            $fallback_language = 'en';
            $amail = $this->readAccountMail($this->settings->get('language', $fallback_language));
            $lang = $this->settings->get('language', $fallback_language);
        }

        // fallback if mail data is still not given
        if (($amail['body'] === '' || $amail['subject'] === '') && $this->areLangVariablesUsedAsFallback()) {
            $lang = $user->getLanguage();
            $tmp_lang = new ilLanguage($lang);

            $mail_subject = $tmp_lang->txt('reg_mail_subject');

            $timelimit = "";
            if (!$user->checkTimeLimit()) {
                $tmp_lang->loadLanguageModule("registration");

                // #6098
                $timelimit_from = new ilDateTime($user->getTimeLimitFrom(), IL_CAL_UNIX);
                $timelimit_until = new ilDateTime($user->getTimeLimitUntil(), IL_CAL_UNIX);
                $timelimit = ilDatePresentation::formatPeriod($timelimit_from, $timelimit_until);
                $timelimit = "\n" . sprintf($tmp_lang->txt('reg_mail_body_timelimit'), $timelimit) . "\n\n";
            }

            // mail body
            $mail_body = $tmp_lang->txt('reg_mail_body_salutation') . ' ' . $user->getFullname() . ",\n\n" .
                $tmp_lang->txt('reg_mail_body_text1') . "\n\n" .
                $tmp_lang->txt('reg_mail_body_text2') . "\n" .
                ILIAS_HTTP_PATH . '/login.php?client_id=' . CLIENT_ID . "\n";
            $mail_body .= $tmp_lang->txt('login') . ': ' . $user->getLogin() . "\n";
            $mail_body .= $tmp_lang->txt('passwd') . ': ' . $this->u_password . "\n";
            $mail_body .= "\n" . $timelimit;
            $mail_body .= $tmp_lang->txt('reg_mail_body_text3') . "\n\r";
            $mail_body .= $user->getProfileAsString($tmp_lang);
        } else {
            $this->addAttachments($amail);

            // replace placeholders
            $mail_subject = $this->replacePlaceholders($amail['subject'], $user, $amail, $lang);
            $mail_body = $this->replacePlaceholders($amail['body'], $user, $amail, $lang);
        }

        $mmail = new ilMimeMail();
        $mmail->From($this->senderFactory->system());
        $mmail->Subject($mail_subject, true);
        $mmail->To($user->getEmail());
        $mmail->Body($mail_body);

        foreach ($this->attachments as $filename => $display_name) {
            $mmail->Attach($filename, '', 'attachment', $display_name);
        }

        $mmail->Send();

        return true;
    }

    public function replacePlaceholders(string $a_string, ilObjUser $a_user, array $a_amail, string $a_lang): string
    {
        global $DIC;
        $tree = $DIC->repositoryTree();
        $ilSetting = $DIC->settings();
        $mustache_factory = $DIC->mail()->mustacheFactory();

        $replacements = [];

        // determine salutation
        switch ($a_user->getGender()) {
            case "f":
                $replacements["MAIL_SALUTATION"] = trim($a_amail["sal_f"]);
                break;
            case "m":
                $replacements["MAIL_SALUTATION"] = trim($a_amail["sal_m"]);
                break;
            default:
                $replacements["MAIL_SALUTATION"] = trim($a_amail["sal_g"]);
        }
        $replacements["LOGIN"] = $a_user->getLogin();
        $replacements["FIRST_NAME"] = $a_user->getFirstname();
        $replacements["LAST_NAME"] = $a_user->getLastname();
        // BEGIN Mail Include E-Mail Address in account mail
        $replacements["EMAIL"] = $a_user->getEmail();
        // END Mail Include E-Mail Address in account mail
        $replacements["PASSWORD"] = $this->getUserPassword();
        $replacements["ILIAS_URL"] = ILIAS_HTTP_PATH . "/login.php?client_id=" . CLIENT_ID;
        $replacements["CLIENT_NAME"] = CLIENT_NAME;
        $replacements["ADMIN_MAIL"] = $ilSetting->get("admin_email");
        $replacements["IF_PASSWORD"] = $this->getUserPassword() != "";
        $replacements["IF_NO_PASSWORD"] = $this->getUserPassword() == "";

        // #13346
        if (!$a_user->getTimeLimitUnlimited()) {
            // #6098
            $replacements["IF_TIMELIMIT"] = !$a_user->getTimeLimitUnlimited();
            $timelimit_from = new ilDateTime($a_user->getTimeLimitFrom(), IL_CAL_UNIX);
            $timelimit_until = new ilDateTime($a_user->getTimeLimitUntil(), IL_CAL_UNIX);
            $timelimit = ilDatePresentation::formatPeriod($timelimit_from, $timelimit_until);
            $replacements["TIMELIMIT"] = $timelimit;
        }

        // target
        $replacements["IF_TARGET"] = false;
        if ($this->http->wrapper()->query()->has('target') &&
            $this->http->wrapper()->query()->retrieve('target', $this->refinery->kindlyTo()->string()) !== ''
        ) {
            $target = $this->http->wrapper()->query()->retrieve('target', $this->refinery->kindlyTo()->string());
            $tarr = explode('_', (string) $target);
            if ($this->repositoryTree->isInTree((int) $tarr[1])) {
                $obj_id = ilObject::_lookupObjId((int) $tarr[1]);
                $type = ilObject::_lookupType($obj_id);
                if ($type === $tarr[0]) {
                    $replacements["TARGET_TITLE"] = ilObject::_lookupTitle($obj_id);
                    $replacements["TARGET"] = ILIAS_HTTP_PATH . '/goto.php?client_id=' . CLIENT_ID . '&target=' . $target;

                    // this looks complicated, but we may have no initilised $lng object here
                    // if mail is send during user creation in authentication
                    $replacements["TARGET_TYPE"] = ilLanguage::_lookupEntry($a_lang, "common", "obj_" . $tarr[0]);
                    $replacements["IF_TARGET"] = true;
                }
            }
        }

        return $mustache_factory->getBasicEngine()->render($a_string, $replacements);
    }

    public function addAttachment(string $a_filename, string $a_display_name): void
    {
        $this->attachments[$a_filename] = $a_display_name;
    }
}
