<?php

declare(strict_types=1);

class_alias(class_exists('ParsedownExtra') ? 'ParsedownExtra' : 'Parsedown', 'ParsedownExtendedParentAlias');

/**
 * Class ParsedownExtended
 *
 * Extended version of Parsedown for customized Markdown parsing.
 * Provides extended parsing capabilities, version checking, and custom configuration options.
 *
 */
// @psalm-suppress UndefinedClass
class ParsedownExtended extends \ParsedownExtendedParentAlias
{
    public const VERSION = '1.4.2';
    public const VERSION_PARSEDOWN_REQUIRED = '1.7.4';
    public const VERSION_PARSEDOWN_EXTRA_REQUIRED = '0.8.1';
    public const MIN_PHP_VERSION = '7.4';

    /** @var array $anchorRegister Registry for anchors generated during parsing */
    private array $anchorRegister = [];

    /** @var array $contentsListArray List of contents generated during parsing */
    private array $contentsListArray = [];

    /** @var int $firstHeadLevel The level of the first header parsed */
    private int $firstHeadLevel = 0;

    /** @var string $contentsListString String representation of the table of contents */
    private string $contentsListString = '';

    /** @var callable|null $createAnchorIDCallback Callback function for anchor creation */
    private $createAnchorIDCallback = null;

    /** @var array $config Configuration options */
    private array $config;

    /** @var array $configSchema Schema for validating configuration options */
    private array $configSchema;

    /** @var bool $legacyMode Flag indicating if legacy compatibility mode is enabled */
    private bool $legacyMode = false;

    /**
     * Constructor for ParsedownExtended.
     *
     * Initializes the class and performs version checks for PHP and Parsedown dependencies.
     */
    public function __construct()
    {
        // Check if the current PHP version meets the minimum requirement
        $this->checkVersion('PHP', PHP_VERSION, self::MIN_PHP_VERSION);

        // Check if the installed Parsedown version meets the minimum requirement
        $this->checkVersion('Parsedown', \Parsedown::version, self::VERSION_PARSEDOWN_REQUIRED);

        if (class_exists('ParsedownExtra')) {
            // Ensure ParsedownExtra meets the version requirement
            $this->checkVersion('ParsedownExtra', \ParsedownExtra::version, self::VERSION_PARSEDOWN_EXTRA_REQUIRED);
            parent::__construct();
        }

        $this->setLegacyMode();

        // Initialize settings with the provided schema
        $this->configSchema = $this->defineConfigSchema();
        $this->config = $this->initializeConfig($this->configSchema);

        // Add support for inline types (e.g., special formatting)
        $this->addInlineType('=', 'Marking');
        $this->addInlineType('+', 'Insertions');
        $this->addInlineType('[', 'Keystrokes');
        $this->addInlineType(['\\', '$'], 'MathNotation');
        $this->addInlineType('^', 'Superscript');
        $this->addInlineType('~', 'Subscript');
        $this->addInlineType(':', 'Emojis');
        $this->addInlineType(['<', '>', '-', '.', "'", '"', '`'], 'Smartypants');
        $this->addInlineType(['(', '.', '+', '!', '?'], 'Typographer');

        // Add support for block types (e.g., blocks of content)
        $this->addBlockType(['\\','$'], 'MathNotation');
        $this->addBlockType('>', 'Alert');

        // Reorganize 'SpecialCharacter' to ensure it is processed last in InlineTypes and BlockTypes
        foreach ($this->InlineTypes as &$list) {
            if (($key = array_search('SpecialCharacter', $list)) !== false) {
                unset($list[$key]);
                $list[] = 'SpecialCharacter'; // Append 'SpecialCharacter' at the end
            }
        }

        foreach ($this->BlockTypes as &$list) {
            if (($key = array_search('SpecialCharacter', $list)) !== false) {
                unset($list[$key]);
                $list[] = 'SpecialCharacter'; // Append 'SpecialCharacter' at the end
            }
        }
    }

    /**
     * Check version compatibility for a specific component.
     *
     * Verifies if the current version of a component (e.g., PHP or Parsedown) meets the required version.
     * Throws an exception if the version is not sufficient.
     *
     * @since 1.3.0
     *
     * @param string $component The name of the component being checked (e.g., 'PHP', 'Parsedown')
     * @param string $currentVersion The current version of the component installed
     * @param string $requiredVersion The minimum required version of the component
     *
     * @throws \Exception If the current version is lower than the required version
     */
    private function checkVersion(string $component, string $currentVersion, string $requiredVersion): void
    {
        // Compare the current version with the required version
        if (version_compare($currentVersion, $requiredVersion) < 0) {
            // Prepare an error message indicating version incompatibility
            $msg_error  = 'Version Error.' . PHP_EOL;
            $msg_error .= "  ParsedownExtended requires a later version of $component." . PHP_EOL;
            $msg_error .= "  - Current version : $currentVersion" . PHP_EOL;
            $msg_error .= "  - Required version: $requiredVersion and later" . PHP_EOL;

            // Throw an exception with the version error message
            throw new \Exception($msg_error);
        }
    }


    /**
     * Method setLegacyMode
     *
     * Sets the legacy mode based on the version of Parsedown.
     *
     * @since 1.3.0
     *
     * @return void
     */
    private function setLegacyMode(): void
    {
        $parsedownVersion = preg_replace('/-.*$/', '', \Parsedown::version);

        // Enable legacy mode if Parsedown version is between 1.7.4 and below 1.8.0
        if (version_compare($parsedownVersion, '1.8.0') < 0 && version_compare($parsedownVersion, '1.7.4') >= 0) {
            $this->legacyMode = true;
        }
    }

    // Inline types
    // -------------------------------------------------------------------------

    /**
     * Processes inline code elements.
     *
     * Handles inline code if it is enabled in the configuration settings.
     *
     * @since 0.1.0
     *
     * @param array $Excerpt The portion of text being parsed
     * @return mixed|null The parsed element or null if not processed
     */
    protected function inlineCode($Excerpt)
    {
        if ($this->config()->get('code') && $this->config()->get('code.inline')) {
            return parent::inlineCode($Excerpt);
        }

        return null;
    }

    /**
     * Processes inline images.
     *
     * Handles inline images if the feature is enabled in the configuration.
     *
     * @since 0.1.0
     *
     * @param array $Excerpt The portion of text being parsed
     * @return mixed|null The parsed image element or null if not processed
     */
    protected function inlineImage($Excerpt)
    {
        if ($this->config()->get('images')) {
            return parent::inlineImage($Excerpt);
        }

        return null;
    }

    /**
     * Processes inline HTML markup.
     *
     * Parses inline HTML if raw HTML is allowed in the configuration.
     *
     * @since 0.1.0
     *
     * @param array $Excerpt The portion of text being parsed
     * @return mixed|null The parsed HTML markup or null if not allowed
     */
    protected function inlineMarkup($Excerpt)
    {
        if ($this->config()->get('allow_raw_html')) {
            return parent::inlineMarkup($Excerpt);
        }

        return null;
    }

    /**
     * Processes inline strikethrough elements.
     *
     * Handles inline strikethrough text if the emphasis is enabled in the configuration.
     *
     * @since 0.1.0
     *
     * @param array $Excerpt The portion of text being parsed
     * @return mixed|null The parsed strikethrough or null if not processed
     */
    protected function inlineStrikethrough($Excerpt)
    {
        if ($this->config()->get('emphasis.strikethroughs') && $this->config()->get('emphasis')) {
            return parent::inlineStrikethrough($Excerpt);
        }

        return null;
    }

    /**
     * Processes inline links.
     *
     * Extends link processing to handle custom link behaviors.
     *
     * @since 0.1.0
     *
     * @param array $Excerpt The portion of text being parsed
     * @return array|null The processed link element or null if not processed
     */
    protected function inlineLink($Excerpt)
    {
        return $this->processLinkElement(parent::inlineLink($Excerpt));
    }

    /**
     * Processes inline URLs.
     *
     * Extends the URL processing to include additional custom behavior, such as modifying the parsed URL element.
     *
     * @since 0.1.0
     *
     * @param array $Excerpt The portion of text being parsed
     * @return array|null The processed URL element or null if not processed
     */
    protected function inlineUrl($Excerpt)
    {
        return $this->processLinkElement(parent::inlineUrl($Excerpt));
    }

    /**
     * Processes inline URL tags.
     *
     * Handles parsing of inline URL tags, adding any custom behavior if needed.
     *
     * @since 0.1.0
     *
     * @param array $Excerpt The portion of text being parsed
     * @return array|null The processed URL tag or null if not processed
     */
    protected function inlineUrlTag($Excerpt)
    {
        return $this->processLinkElement(parent::inlineUrlTag($Excerpt));
    }



    /**
     * Processes inline email tags.
     *
     * Handles email links if the feature is enabled in the configuration.
     *
     * @since 0.1.0
     *
     * @param array $Excerpt The portion of text being parsed
     * @return mixed|null The parsed email tag or null if links are disabled
     */
    protected function inlineEmailTag($Excerpt)
    {
        if (!$this->config()->get('links') || !$this->config()->get('links.email_links')) {
            return null;
        }

        $Excerpt = parent::inlineEmailTag($Excerpt);

        if (isset($Excerpt['element']['attributes']['href'])) {
            $Excerpt['element']['attributes']['target'] = '_blank';
        }

        return $Excerpt;
    }


    /**
     * Processes link elements to add behavior control attributes.
     *
     * Extends parsed Markdown link elements to include attributes such as `nofollow`, `noopener`, and `noreferrer`
     * based on the configuration settings, particularly for external links. This helps control search engine indexing,
     * external page behavior, and referrer privacy.
     *
     * @since 1.3.0
     *
     * @param array $Excerpt The portion of text representing the link element.
     * @return array|null Modified link element with added attributes or null if the link is disallowed.
     */
    protected function processLinkElement($Excerpt)
    {
        if (!$this->config()->get('links') || !$Excerpt || !isset($Excerpt['element']['attributes']['href'])) {
            return null;
        }

        if (isset($Excerpt['element']['attributes']['href'])) {
            // Get the href attribute
            $href = $Excerpt['element']['attributes']['href'];

            // Check if link is an external link
            $isExternal = $this->isExternalLink($href);

            if ($isExternal === true) {
                // Check if external links are disabled
                if (!$this->config()->get('links.external_links')) {
                    return null;
                }

                $rel = [];

                // Add nofollow attribute if specified in the configuration
                if ($this->config()->get('links.external_links.nofollow')) {
                    $rel[] = 'nofollow';
                }

                // Add noopener attribute if specified in the configuration
                if ($this->config()->get('links.external_links.noopener')) {
                    $rel[] = 'noopener';
                }

                // Add noreferrer attribute if specified in the configuration
                if ($this->config()->get('links.external_links.noreferrer')) {
                    $rel[] = 'noreferrer';
                }

                // Add target attribute with '_blank' value
                if ($this->config()->get('links.external_links.open_in_new_window')) {
                    $Excerpt['element']['attributes']['target'] = '_blank';
                }

                // Add rel attribute with values from the $rel array
                if (!empty($rel)) {
                    $Excerpt['element']['attributes']['rel'] = implode(' ', $rel);
                }
            }
        }

        return $Excerpt;
    }

    /**
     * Determines if a given link is an external link.
     *
     * Checks if the link is either protocol-relative (starts with `//`) or absolute (`http://` or `https://`)
     * and if the host differs from the current server's host. It also checks against a list of internal hosts to identify external links.
     *
     * @since 1.3.0
     *
     * @param string $href The URL to check.
     * @return bool Returns true if the link is external, false otherwise.
     */
    private function isExternalLink($href)
    {
        // Check if the URL is protocol-relative (e.g., starts with `//`)
        $isProtocolRelative = preg_match('/^\/\//', $href);

        // Check if the URL is an absolute URL (starts with http:// or https://)
        $isAbsolute = preg_match('/^https?:\/\//i', $href);

        if ($isProtocolRelative || $isAbsolute) {
            // Extract the host part of the URL
            $host = parse_url($href, PHP_URL_HOST);

            // Check if the domain matches the current domain
            if ($host && $host !== $_SERVER['HTTP_HOST']) {

                // Remove 'www.' from the host to get the base domain name
                $domain = preg_replace('/^www\\./', '', $host);

                // Get the list of internal hosts from the configuration
                $internalHosts = $this->config()->get('links.external_links.internal_hosts');

                // Return false if the link is considered internal based on the configuration
                if (in_array($domain, $internalHosts)) {
                    return false;
                }
                return true; // If the link is not internal, it is external
            }
        }

        return false;
    }

    /**
     * Processes inline emphasis elements.
     *
     * Handles inline emphasis (like bold or italics) if enabled in the configuration.
     *
     * @since 0.1.0
     *
     * @param array $Excerpt The portion of text being parsed
     * @return array|null The parsed emphasis or null if not processed
     */
    protected function inlineEmphasis($Excerpt)
    {
        if (!$this->config()->get('emphasis') || !isset($Excerpt['text'][1])) {
            return null; // If emphasis is disabled or the excerpt is too short, return null
        }

        $marker = $Excerpt['text'][0]; // Extract the marker character ('*', '_', etc.)

        // Check if the text matches bold emphasis using the marker
        if ($this->config()->get('emphasis.bold') && preg_match($this->StrongRegex[$marker], $Excerpt['text'], $matches)) {
            $emphasis = 'strong'; // Use 'strong' for bold text
        }
        // Check if the text matches italic emphasis using the marker
        elseif ($this->config()->get('emphasis.italic') && preg_match($this->EmRegex[$marker], $Excerpt['text'], $matches)) {
            $emphasis = 'em'; // Use 'em' for italic text
        } else {
            return null; // No valid emphasis match found
        }

        // Return the parsed emphasis element
        return [
            'extent' => strlen($matches[0]), // Length of the matched emphasis text
            'element' => [
                'name' => $emphasis, // 'strong' for bold or 'em' for italics
                'handler' => 'line', // Handler for further inline processing
                'text' => $matches[1], // The emphasized content
            ],
        ];
    }

    /**
     * Processes inline marking elements.
     *
     * Handles inline marking by using double equal signs (`==text==`). This will convert the marked text
     * into an HTML `<mark>` tag if the feature is enabled in the configuration.
     *
     * @since 1.2.0
     *
     * @param array $Excerpt The portion of text being parsed to identify marking.
     * @return array|null The parsed marking element or null if marking is disabled or not applicable.
     */
    protected function inlineMarking(array $Excerpt): ?array
    {
        // Check if marking is enabled in the configuration settings
        if (!$this->config()->get('emphasis.mark') || !$this->config()->get('emphasis')) {
            return null; // Return null if marking or emphasis is disabled
        }

        // Match the double equal signs for marking (`==text==`) using regex
        if (preg_match('/^==((?:\\\\\=|[^=]|=[^=]*=)+?)==(?!=)/s', $Excerpt['text'], $matches)) {
            // Return the parsed marking element
            return [
                'extent' => strlen($matches[0]), // The length of the matched marking text
                'element' => [
                    'name' => 'mark', // The HTML tag used for marking
                    'text' => $matches[1], // The content inside the marking
                ],
            ];
        }

        return null; // If no match is found, return null
    }

    /**
     * Processes inline insertion elements.
     *
     * Handles inline insertions denoted by double plus signs (`++text++`). If enabled in the configuration,
     * this will convert the marked text into an HTML `<ins>` tag, which is commonly used to indicate additions.
     *
     * @since 1.2.0
     *
     * @param array $Excerpt The portion of text being parsed to identify insertions.
     * @return array|null The parsed insertion element or null if insertions are disabled or not applicable.
     */
    protected function inlineInsertions(array $Excerpt): ?array
    {
        // Check if insertions are enabled in the configuration settings
        if (!$this->config()->get('emphasis.insertions') || !$this->config()->get('emphasis')) {
            return null; // Return null if insertions or general emphasis is disabled
        }

        // Match the double plus signs for insertions (`++text++`) using regex
        if (preg_match('/^\+\+((?:\\\\\+|[^\+]|\+[^\+]*\+)+?)\+\+(?!\+)/s', $Excerpt['text'], $matches)) {
            // Return the parsed insertion element
            return [
                'extent' => strlen($matches[0]), // The length of the matched insertion text
                'element' => [
                    'name' => 'ins', // The HTML tag used for insertions
                    'text' => $matches[1], // The content inside the insertion
                ],
            ];
        }

        return null; // If no match is found, return null
    }

    /**
     * Processes inline keystroke elements.
     *
     * Handles inline keystrokes denoted by double square brackets (`[[text]]`). If enabled in the configuration,
     * this will convert the enclosed text into an HTML `<kbd>` tag, which is typically used to represent user input or keystrokes.
     *
     * @since 1.0.0
     *
     * @param array $Excerpt The portion of text being parsed to identify keystrokes.
     * @return array|null The parsed keystroke element or null if keystrokes are disabled or not applicable.
     */
    protected function inlineKeystrokes(array $Excerpt): ?array
    {
        // Check if keystrokes are enabled in the configuration settings
        if (!$this->config()->get('emphasis.keystrokes') || !$this->config()->get('emphasis')) {
            return null; // Return null if keystrokes or general emphasis is disabled
        }

        // Match the double square brackets for keystrokes (`[[text]]`) using regex
        if (preg_match('/^(?<!\[)(?:\[\[([^\[\]]*|[\[\]])\]\])(?!\])/s', $Excerpt['text'], $matches)) {
            // Return the parsed keystroke element
            return [
                'extent' => strlen($matches[0]), // The length of the matched keystroke text
                'element' => [
                    'name' => 'kbd', // The HTML tag used for keystrokes
                    'text' => $matches[1], // The content inside the keystroke brackets
                ],
            ];
        }

        return null; // If no match is found, return null
    }


    /**
     * Processes inline superscript elements.
     *
     * Handles inline superscript denoted by a caret symbol (`^text^`). If enabled in the configuration,
     * this will convert the marked text into an HTML `<sup>` tag, which is typically used for superscripts in text.
     *
     * @since 1.0.0
     *
     * @param array $Excerpt The portion of text being parsed to identify superscript.
     * @return array|null The parsed superscript element or null if superscript is disabled or not applicable.
     */
    protected function inlineSuperscript(array $Excerpt): ?array
    {
        // Check if superscript is enabled in the configuration settings
        if (!$this->config()->get('emphasis.superscript') || !$this->config()->get('emphasis')) {
            return null; // Return null if superscript or general emphasis is disabled
        }

        // Match the caret symbols for superscript (`^text^`) using regex
        if (preg_match('/^\^((?:\\\\\\^|[^\^]|\^[^\^]+?\^\^)+?)\^(?!\^)/s', $Excerpt['text'], $matches)) {
            // Return the parsed superscript element
            return [
                'extent' => strlen($matches[0]), // The length of the matched superscript text
                'element' => [
                    'name' => 'sup', // The HTML tag used for superscript
                    'text' => $matches[1], // The content inside the superscript markers
                ],
            ];
        }

        return null; // If no match is found, return null
    }


    /**
     * Processes inline subscript elements.
     *
     * Handles inline subscript denoted by a tilde (`~text~`). If enabled in the configuration,
     * this will convert the marked text into an HTML `<sub>` tag, which is typically used for subscripts in text.
     *
     * @since 1.0.0
     *
     * @param array $Excerpt The portion of text being parsed to identify subscript.
     * @return array|null The parsed subscript element or null if subscript is disabled or not applicable.
     */
    protected function inlineSubscript(array $Excerpt): ?array
    {
        // Check if subscript is enabled in the configuration settings
        if (!$this->config()->get('emphasis.subscript') || !$this->config()->get('emphasis')) {
            return null; // Return null if subscript or general emphasis is disabled
        }

        // Match the tilde symbols for subscript (`~text~`) using regex
        if (preg_match('/^~((?:\\\\~|[^~]|~~[^~]*~~)+?)~(?!~)/s', $Excerpt['text'], $matches)) {
            // Return the parsed subscript element
            return [
                'extent' => strlen($matches[0]), // The length of the matched subscript text
                'element' => [
                    'name' => 'sub', // The HTML tag used for subscript
                    'text' => $matches[1], // The content inside the subscript markers
                ],
            ];
        }

        return null; // If no match is found, return null
    }




    /**
     * Processes inline math notation elements.
     *
     * Handles inline math notation using specific delimiters (e.g., `$...$`, `\\(...\\)`). If enabled in the configuration,
     * this function matches math notation within the specified delimiters and processes it accordingly.
     *
     * @since 1.1.2
     *
     * @param array $Excerpt The portion of text being parsed to identify math notation.
     * @return array|null The parsed math notation element or null if math parsing is disabled or not applicable.
     */
    protected function inlineMathNotation($Excerpt)
    {
        // Check if parsing of math notation is enabled in the configuration settings
        if (!$this->config()->get('math') || !$this->config()->get('math.inline')) {
            return null; // Return null if math or inline math is disabled
        }

        // Check if the excerpt has enough characters to proceed
        if (!isset($Excerpt['text'][1])) {
            return null; // Return null if there is insufficient text for math notation
        }

        // Check if there is whitespace before the excerpt (ensures math is not in the middle of a word)
        if ($Excerpt['before'] !== '' && preg_match('/\s/', $Excerpt['before']) === 0) {
            return null; // Return null if the math notation is not preceded by whitespace
        }

        // Iterate through the inline math delimiters (e.g., `$...$`, `\\(...\\)`)
        foreach ($this->config()->get('math.inline.delimiters') as $config) {
            $leftMarker = preg_quote($config['left'], '/');  // Escape the left delimiter for use in regex
            $rightMarker = preg_quote($config['right'], '/'); // Escape the right delimiter for use in regex

            // Create the regex pattern for matching math notation
            if ($config['left'][0] === '\\' || strlen($config['left']) > 1) {
                $regex = '/^(?<!\S)' . $leftMarker . '(?![\r\n])((?:\\\\' . $rightMarker . '|\\\\' . $leftMarker . '|[^\r\n])+?)' . $rightMarker . '(?![^\s,.])/s';
            } else {
                $regex = '/^(?<!\S)' . $leftMarker . '(?![\r\n])((?:\\\\' . $rightMarker . '|\\\\' . $leftMarker . '|[^' . $rightMarker . '\r\n])+?)' . $rightMarker . '(?![^\s,.])/s';
            }

            // Match the regular expression pattern against the excerpt
            if (preg_match($regex, $Excerpt['text'], $matches)) {
                // Return the parsed math element
                return [
                    'extent' => strlen($matches[0]), // The length of the matched math notation
                    'element' => [
                        'text' => $matches[0], // The matched math content
                    ],
                ];
            }
        }

        return null; // If no match is found, return null
    }


    /**
     * Processes inline escape sequences.
     *
     * Handles escape sequences to allow special characters to be rendered as literals instead of being interpreted.
     * Specifically, if a character is preceded by a backslash, it is treated as an escaped character.
     * Additionally, it ensures that math delimiters are not mistakenly escaped.
     *
     * @since 0.1.0
     *
     * @param array $Excerpt The portion of text being parsed to identify escape sequences.
     * @return array|null The parsed escape sequence element or null if no valid escape sequence is found.
     */
    protected function inlineEscapeSequence($Excerpt)
    {
        // If math is enabled, check for any inline math delimiters that might need special handling
        if ($this->config()->get('math')) {
            foreach ($this->config()->get('math.inline.delimiters') as $config) {
                $leftMarker = preg_quote($config['left'], '/');  // Escape the left delimiter for use in regex
                $rightMarker = preg_quote($config['right'], '/'); // Escape the right delimiter for use in regex

                // Create the regex pattern for matching math notation
                if ($config['left'][0] === '\\' || strlen($config['left']) > 1) {
                    $regex = '/^(?<!\S)' . $leftMarker . '(?![\r\n])((?:\\\\' . $rightMarker . '|\\\\' . $leftMarker . '|[^\r\n])+?)' . $rightMarker . '(?![^\s,.])/s';
                } else {
                    $regex = '/^(?<!\S)' . $leftMarker . '(?![\r\n])((?:\\\\' . $rightMarker . '|\\\\' . $leftMarker . '|[^' . $rightMarker . '\r\n])+?)' . $rightMarker . '(?![^\s,.])/s';
                }

                // If a math notation match is found, return null as it's not an escape sequence
                if (preg_match($regex, $Excerpt['text'])) {
                    return null;
                }
            }
        }

        // Check if the character following the backslash is a special character that should be escaped
        if (isset($Excerpt['text'][1]) && in_array($Excerpt['text'][1], $this->specialCharacters)) {
            // Return the escaped character
            return [
                'markup' => $Excerpt['text'][1], // The character to be escaped
                'extent' => 2, // The length of the escape sequence (backslash + character)
            ];
        }

        // If no valid escape sequence is found, return null
        return null;
    }


    /**
     * Processes inline typographic substitutions.
     *
     * This function handles typographic improvements, such as replacing plain text with their typographic equivalents.
     * It processes symbols like (c) to ©, (r) to ®, and smart ellipses based on the user's configuration.
     * This is particularly useful for enhancing readability by applying typographer rules.
     *
     * @since 1.0.1
     *
     * @param array $Excerpt The portion of text being parsed for typographic substitutions.
     * @return array|null The parsed typographic substitutions or null if the typographer feature is disabled.
     */
    protected function inlineTypographer(array $Excerpt): ?array
    {
        // Check if typographer is enabled in the configuration settings
        if (!$this->config()->get('typographer')) {
            return null; // Return null if the typographer is disabled
        }

        // Check if smartypants and smart ellipses settings are enabled
        $ellipses = $this->config()->get('smartypants') && $this->config()->get('smartypants.smart_ellipses')
            ? html_entity_decode($this->config()->get('smartypants.substitutions.ellipses'))
            : '...'; // Use smart ellipses if enabled, otherwise use '...'

        // Define substitutions for various typographic symbols
        $substitutions = [
            '/\(c\)/i' => html_entity_decode('&copy;'), // Replace (c) with © symbol
            '/\(r\)/i' => html_entity_decode('&reg;'), // Replace (r) with ® symbol
            '/\(tm\)/i' => html_entity_decode('&trade;'), // Replace (tm) with ™ symbol
            '/\(p\)/i' => html_entity_decode('&para;'), // Replace (p) with ¶ symbol (paragraph)
            '/\+-/i' => html_entity_decode('&plusmn;'), // Replace +- with ± symbol
            '/\!\.{3,}/i' => '!..', // Replace more than three exclamation points with '!..'
            '/\?\.{3,}/i' => '?..', // Replace more than three question marks with '?..'
            '/\.{2,}/i' => $ellipses, // Replace ellipses with either smart ellipses or '...'
        ];

        // Apply substitutions using regular expressions
        $result = preg_replace(array_keys($substitutions), array_values($substitutions), $Excerpt['text'], -1, $count);

        // If substitutions were made, return the modified text
        if ($count > 0) {
            return [
                'extent' => strlen($Excerpt['text']), // The length of the original excerpt text
                'element' => [
                    'text' => $result, // The modified text after applying typographic substitutions
                ],
            ];
        }

        return null; // If no substitutions were made, return null
    }


    /**
     * Processes inline Smartypants substitutions.
     *
     * This function handles typographic improvements to the text, such as converting straight quotes to curly quotes,
     * converting double angle quotes, converting dashes into em or en dashes, and ellipses into the proper character.
     * These changes enhance readability and align text formatting with common typographic standards.
     *
     * @since 1.0.0
     *
     * @param array $Excerpt The portion of text being parsed for Smartypants substitutions.
     * @return array|null The parsed Smartypants substitution or null if Smartypants is disabled.
     */
    protected function inlineSmartypants($Excerpt)
    {
        // Check if Smartypants is enabled in the configuration settings
        if (!$this->config()->get('smartypants')) {
            return null; // Return null if Smartypants is disabled
        }

        // Substitutions: Load the characters to use for the specific Smartypants transformations
        $substitutions = [
            'left_double_quote' => html_entity_decode($this->config()->get('smartypants.substitutions.left_double_quote')),
            'right_double_quote' => html_entity_decode($this->config()->get('smartypants.substitutions.right_double_quote')),
            'left_single_quote' => html_entity_decode($this->config()->get('smartypants.substitutions.left_single_quote')),
            'right_single_quote' => html_entity_decode($this->config()->get('smartypants.substitutions.right_single_quote')),
            'left_angle_quote' => html_entity_decode($this->config()->get('smartypants.substitutions.left_angle_quote')),
            'right_angle_quote' => html_entity_decode($this->config()->get('smartypants.substitutions.right_angle_quote')),
            'mdash' => html_entity_decode($this->config()->get('smartypants.substitutions.mdash')),
            'ndash' => html_entity_decode($this->config()->get('smartypants.substitutions.ndash')),
            'ellipses' => html_entity_decode($this->config()->get('smartypants.substitutions.ellipses')),
        ];

        // Define patterns for various Smartypants substitutions
        $patterns = [
            'smart_backticks' => [
                'pattern' => '/^(``)(?!\s)([^"\'`]{1,})(\'\')/i',
                'callback' => function ($matches) use ($substitutions, $Excerpt) {
                    if (strlen(trim($Excerpt['before'])) > 0) {
                        return null; // Skip if the backticks do not start at the beginning
                    }

                    // Return transformed text with left and right double quotes
                    return [
                        'extent' => strlen($matches[0]),
                        'element' => [
                            'text' => $substitutions['left_double_quote'] . $matches[2] . $substitutions['right_double_quote'],
                        ],
                    ];
                },
            ],
            'smart_quotes' => [
                'pattern' => '/^(")(?!\s)([^"]+)(")|^(?<!\w)(\')(?!\s)([^\']+)(\')/i',
                'callback' => function ($matches) use ($substitutions, $Excerpt) {
                    if (strlen(trim($Excerpt['before'])) > 0) {
                        return null; // Skip if quotes are in the middle of a word
                    }

                    // Check if the match is for single or double quotes and return transformed text
                    if ("'" === $matches[1]) {
                        return [
                            'extent' => strlen($matches[0]),
                            'element' => [
                                'text' => $substitutions['left_single_quote'] . $matches[2] . $substitutions['right_single_quote'],
                            ],
                        ];
                    }

                    if ('"' === $matches[1]) {
                        return [
                            'extent' => strlen($matches[0]),
                            'element' => [
                                'text' => $substitutions['left_double_quote'] . $matches[2] . $substitutions['right_double_quote'],
                            ],
                        ];
                    }
                },
            ],
            'smart_angled_quotes' => [
                'pattern' => '/^(<{2})(?!\s)([^<>]+)(>{2})/i',
                'callback' => function ($matches) use ($substitutions, $Excerpt) {
                    if (strlen(trim($Excerpt['before'])) > 0) {
                        return null; // Skip if angled quotes do not start at the beginning
                    }

                    // Return transformed text with left and right angle quotes
                    return [
                        'extent' => strlen($matches[0]),
                        'element' => [
                            'text' => $substitutions['left_angle_quote'] . $matches[2] . $substitutions['right_angle_quote'],
                        ],
                    ];
                },
            ],
            'smart_dashes' => [
                'pattern' => '/^(-{2,3})/i',
                'callback' => function ($matches) use ($substitutions) {
                    // Replace double dashes with ndash or triple dashes with mdash
                    if ('---' === $matches[1]) {
                        return [
                            'extent' => strlen($matches[0]),
                            'element' => [
                                'text' => $substitutions['mdash'],
                            ],
                        ];
                    }

                    if ('--' === $matches[1]) {
                        return [
                            'extent' => strlen($matches[0]),
                            'element' => [
                                'text' => $substitutions['ndash'],
                            ],
                        ];
                    }
                },
            ],
            'smart_ellipses' => [
                'pattern' => '/^(?<!\.)(\.{3})(?!\.)/i',
                'callback' => function ($matches) use ($substitutions) {
                    // Replace three dots with an ellipsis
                    return [
                        'extent' => strlen($matches[0]),
                        'element' => [
                            'text' => $substitutions['ellipses'],
                        ],
                    ];
                },
            ],
        ];

        // Iterate over each pattern and apply the corresponding callback if a match is found
        foreach ($patterns as $key => $value) {
            if ($this->config()->get('smartypants.' . $key) && preg_match($value['pattern'], $Excerpt['text'], $matches)) {
                $matches = array_values(array_filter($matches)); // Filter out empty matches
                return $value['callback']($matches); // Return the transformed text using the callback
            }
        }

        // If no substitutions were made, return null
        return null;
    }


    /**
     * Processes inline emoji replacements.
     *
     * This function handles the conversion of text-based emoji shortcuts (e.g., `:smile:`) to their corresponding emoji characters (e.g., 😄).
     * Emojis are replaced based on a predefined emoji map if the emoji feature is enabled in the configuration.
     *
     * @since 1.0.0
     *
     * @param array $Excerpt The portion of text being parsed to identify emoji codes.
     * @return array|null The parsed emoji element or null if emojis are disabled or no match is found.
     */
    protected function inlineEmojis(array $Excerpt): ?array
    {
        // Check if emoji processing is enabled in the configuration settings
        if (!$this->config()->get('emojis')) {
            return null; // Return null if emoji replacement is disabled
        }

        // Define a mapping of emoji codes to their corresponding Unicode characters
        $emojiMap = [
            "grinning_face" => "😀", "grinning_face_with_big_eyes" => "😃", "grinning_face_with_smiling_eyes" => "😄", "beaming_face_with_smiling_eyes" => "😁",
            "grinning_squinting_face" => "😆", "grinning_face_with_sweat" => "😅", "rolling_on_the_floor_laughing" => "🤣", "face_with_tears_of_joy" => "😂",
            "slightly_smiling_face" => "🙂", "upside_down_face" => "🙃", "melting_face" => "🫠", "winking_face" => "😉",
            "smiling_face_with_smiling_eyes" => "😊", "smiling_face_with_halo" => "😇", "smiling_face_with_hearts" => "🥰", "smiling_face_with_heart_eyes" => "😍",
            "star_struck" => "🤩", "face_blowing_a_kiss" => "😘", "kissing_face" => "😗", "smiling_face" => "☺️",
            "kissing_face_with_closed_eyes" => "😚", "kissing_face_with_smiling_eyes" => "😙", "smiling_face_with_tear" => "🥲", "face_savoring_food" => "😋",
            "face_with_tongue" => "😛", "winking_face_with_tongue" => "😜", "zany_face" => "🤪", "squinting_face_with_tongue" => "😝",
            "money_mouth_face" => "🤑", "smiling_face_with_open_hands" => "🤗", "face_with_hand_over_mouth" => "🤭", "face_with_open_eyes_and_hand_over_mouth" => "🫢",
            "face_with_peeking_eye" => "🫣", "shushing_face" => "🤫", "thinking_face" => "🤔", "saluting_face" => "🫡",
            "zipper_mouth_face" => "🤐", "face_with_raised_eyebrow" => "🤨", "neutral_face" => "😐", "expressionless_face" => "😑",
            "face_without_mouth" => "😶", "dotted_line_face" => "🫥", "face_in_clouds" => "😶‍🌫️", "smirking_face" => "😏",
            "unamused_face" => "😒", "face_with_rolling_eyes" => "🙄", "grimacing_face" => "😬", "face_exhaling" => "😮‍💨",
            "lying_face" => "🤥", "shaking_face" => "🫨", "head_shaking_horizontally" => "🙂‍↔️", "head_shaking_vertically" => "🙂‍↕️",
            "relieved_face" => "😌", "pensive_face" => "😔", "sleepy_face" => "😪", "drooling_face" => "🤤",
            "sleeping_face" => "😴", "face_with_bags_under_eyes" => "🫩", "face_with_medical_mask" => "😷", "face_with_thermometer" => "🤒",
            "face_with_head_bandage" => "🤕", "nauseated_face" => "🤢", "face_vomiting" => "🤮", "sneezing_face" => "🤧",
            "hot_face" => "🥵", "cold_face" => "🥶", "woozy_face" => "🥴", "face_with_crossed_out_eyes" => "😵",
            "face_with_spiral_eyes" => "😵‍💫", "exploding_head" => "🤯", "cowboy_hat_face" => "🤠", "partying_face" => "🥳",
            "disguised_face" => "🥸", "smiling_face_with_sunglasses" => "😎", "nerd_face" => "🤓", "face_with_monocle" => "🧐",
            "confused_face" => "😕", "face_with_diagonal_mouth" => "🫤", "worried_face" => "😟", "slightly_frowning_face" => "🙁",
            "frowning_face" => "☹️", "face_with_open_mouth" => "😮", "hushed_face" => "😯", "astonished_face" => "😲",
            "flushed_face" => "😳", "pleading_face" => "🥺", "face_holding_back_tears" => "🥹", "frowning_face_with_open_mouth" => "😦",
            "anguished_face" => "😧", "fearful_face" => "😨", "anxious_face_with_sweat" => "😰", "sad_but_relieved_face" => "😥",
            "crying_face" => "😢", "loudly_crying_face" => "😭", "face_screaming_in_fear" => "😱", "confounded_face" => "😖",
            "persevering_face" => "😣", "disappointed_face" => "😞", "downcast_face_with_sweat" => "😓", "weary_face" => "😩",
            "tired_face" => "😫", "yawning_face" => "🥱", "face_with_steam_from_nose" => "😤", "enraged_face" => "😡",
            "angry_face" => "😠", "face_with_symbols_on_mouth" => "🤬", "smiling_face_with_horns" => "😈", "angry_face_with_horns" => "👿",
            "skull" => "💀", "skull_and_crossbones" => "☠️", "pile_of_poo" => "💩", "clown_face" => "🤡",
            "ogre" => "👹", "goblin" => "👺", "ghost" => "👻", "alien" => "👽",
            "alien_monster" => "👾", "robot" => "🤖", "grinning_cat" => "😺", "grinning_cat_with_smiling_eyes" => "😸",
            "cat_with_tears_of_joy" => "😹", "smiling_cat_with_heart_eyes" => "😻", "cat_with_wry_smile" => "😼", "kissing_cat" => "😽",
            "weary_cat" => "🙀", "crying_cat" => "😿", "pouting_cat" => "😾", "see_no_evil_monkey" => "🙈",
            "hear_no_evil_monkey" => "🙉", "speak_no_evil_monkey" => "🙊", "love_letter" => "💌", "heart_with_arrow" => "💘",
            "heart_with_ribbon" => "💝", "sparkling_heart" => "💖", "growing_heart" => "💗", "beating_heart" => "💓",
            "revolving_hearts" => "💞", "two_hearts" => "💕", "heart_decoration" => "💟", "heart_exclamation" => "❣️",
            "broken_heart" => "💔", "heart_on_fire" => "❤️‍🔥", "mending_heart" => "❤️‍🩹", "red_heart" => "❤️",
            "pink_heart" => "🩷", "orange_heart" => "🧡", "yellow_heart" => "💛", "green_heart" => "💚",
            "blue_heart" => "💙", "light_blue_heart" => "🩵", "purple_heart" => "💜", "brown_heart" => "🤎",
            "black_heart" => "🖤", "grey_heart" => "🩶", "white_heart" => "🤍", "kiss_mark" => "💋",
            "hundred_points" => "💯", "anger_symbol" => "💢", "collision" => "💥", "dizzy" => "💫",
            "sweat_droplets" => "💦", "dashing_away" => "💨", "hole" => "🕳️", "speech_balloon" => "💬",
            "eye_in_speech_bubble" => "👁️‍🗨️", "left_speech_bubble" => "🗨️", "right_anger_bubble" => "🗯️", "thought_balloon" => "💭",
            "zzz" => "💤", "waving_hand" => "👋", "raised_back_of_hand" => "🤚", "hand_with_fingers_splayed" => "🖐️",
            "raised_hand" => "✋", "vulcan_salute" => "🖖", "rightwards_hand" => "🫱", "leftwards_hand" => "🫲",
            "palm_down_hand" => "🫳", "palm_up_hand" => "🫴", "leftwards_pushing_hand" => "🫷", "rightwards_pushing_hand" => "🫸",
            "ok_hand" => "👌", "pinched_fingers" => "🤌", "pinching_hand" => "🤏", "victory_hand" => "✌️",
            "crossed_fingers" => "🤞", "hand_with_index_finger_and_thumb_crossed" => "🫰", "love_you_gesture" => "🤟", "sign_of_the_horns" => "🤘",
            "call_me_hand" => "🤙", "backhand_index_pointing_left" => "👈", "backhand_index_pointing_right" => "👉", "backhand_index_pointing_up" => "👆",
            "middle_finger" => "🖕", "backhand_index_pointing_down" => "👇", "index_pointing_up" => "☝️", "index_pointing_at_the_viewer" => "🫵",
            "thumbs_up" => "👍", "thumbs_down" => "👎", "raised_fist" => "✊", "oncoming_fist" => "👊",
            "left_facing_fist" => "🤛", "right_facing_fist" => "🤜", "clapping_hands" => "👏", "raising_hands" => "🙌",
            "heart_hands" => "🫶", "open_hands" => "👐", "palms_up_together" => "🤲", "handshake" => "🤝",
            "folded_hands" => "🙏", "writing_hand" => "✍️", "nail_polish" => "💅", "selfie" => "🤳",
            "flexed_biceps" => "💪", "mechanical_arm" => "🦾", "mechanical_leg" => "🦿", "leg" => "🦵",
            "foot" => "🦶", "ear" => "👂", "ear_with_hearing_aid" => "🦻", "nose" => "👃",
            "brain" => "🧠", "anatomical_heart" => "🫀", "lungs" => "🫁", "tooth" => "🦷",
            "bone" => "🦴", "eyes" => "👀", "eye" => "👁️", "tongue" => "👅",
            "mouth" => "👄", "biting_lip" => "🫦", "baby" => "👶", "child" => "🧒",
            "boy" => "👦", "girl" => "👧", "person" => "🧑", "person_blond_hair" => "👱",
            "man" => "👨", "person_beard" => "🧔", "man_beard" => "🧔‍♂️", "woman_beard" => "🧔‍♀️",
            "man_red_hair" => "👨‍🦰", "man_curly_hair" => "👨‍🦱", "man_white_hair" => "👨‍🦳", "man_bald" => "👨‍🦲",
            "woman" => "👩", "woman_red_hair" => "👩‍🦰", "person_red_hair" => "🧑‍🦰", "woman_curly_hair" => "👩‍🦱",
            "person_curly_hair" => "🧑‍🦱", "woman_white_hair" => "👩‍🦳", "person_white_hair" => "🧑‍🦳", "woman_bald" => "👩‍🦲",
            "person_bald" => "🧑‍🦲", "woman_blond_hair" => "👱‍♀️", "man_blond_hair" => "👱‍♂️", "older_person" => "🧓",
            "old_man" => "👴", "old_woman" => "👵", "person_frowning" => "🙍", "man_frowning" => "🙍‍♂️",
            "woman_frowning" => "🙍‍♀️", "person_pouting" => "🙎", "man_pouting" => "🙎‍♂️", "woman_pouting" => "🙎‍♀️",
            "person_gesturing_no" => "🙅", "man_gesturing_no" => "🙅‍♂️", "woman_gesturing_no" => "🙅‍♀️", "person_gesturing_ok" => "🙆",
            "man_gesturing_ok" => "🙆‍♂️", "woman_gesturing_ok" => "🙆‍♀️", "person_tipping_hand" => "💁", "man_tipping_hand" => "💁‍♂️",
            "woman_tipping_hand" => "💁‍♀️", "person_raising_hand" => "🙋", "man_raising_hand" => "🙋‍♂️", "woman_raising_hand" => "🙋‍♀️",
            "deaf_person" => "🧏", "deaf_man" => "🧏‍♂️", "deaf_woman" => "🧏‍♀️", "person_bowing" => "🙇",
            "man_bowing" => "🙇‍♂️", "woman_bowing" => "🙇‍♀️", "person_facepalming" => "🤦", "man_facepalming" => "🤦‍♂️",
            "woman_facepalming" => "🤦‍♀️", "person_shrugging" => "🤷", "man_shrugging" => "🤷‍♂️", "woman_shrugging" => "🤷‍♀️",
            "health_worker" => "🧑‍⚕️", "man_health_worker" => "👨‍⚕️", "woman_health_worker" => "👩‍⚕️", "student" => "🧑‍🎓",
            "man_student" => "👨‍🎓", "woman_student" => "👩‍🎓", "teacher" => "🧑‍🏫", "man_teacher" => "👨‍🏫",
            "woman_teacher" => "👩‍🏫", "judge" => "🧑‍⚖️", "man_judge" => "👨‍⚖️", "woman_judge" => "👩‍⚖️",
            "farmer" => "🧑‍🌾", "man_farmer" => "👨‍🌾", "woman_farmer" => "👩‍🌾", "cook" => "🧑‍🍳",
            "man_cook" => "👨‍🍳", "woman_cook" => "👩‍🍳", "mechanic" => "🧑‍🔧", "man_mechanic" => "👨‍🔧",
            "woman_mechanic" => "👩‍🔧", "factory_worker" => "🧑‍🏭", "man_factory_worker" => "👨‍🏭", "woman_factory_worker" => "👩‍🏭",
            "office_worker" => "🧑‍💼", "man_office_worker" => "👨‍💼", "woman_office_worker" => "👩‍💼", "scientist" => "🧑‍🔬",
            "man_scientist" => "👨‍🔬", "woman_scientist" => "👩‍🔬", "technologist" => "🧑‍💻", "man_technologist" => "👨‍💻",
            "woman_technologist" => "👩‍💻", "singer" => "🧑‍🎤", "man_singer" => "👨‍🎤", "woman_singer" => "👩‍🎤",
            "artist" => "🧑‍🎨", "man_artist" => "👨‍🎨", "woman_artist" => "👩‍🎨", "pilot" => "🧑‍✈️",
            "man_pilot" => "👨‍✈️", "woman_pilot" => "👩‍✈️", "astronaut" => "🧑‍🚀", "man_astronaut" => "👨‍🚀",
            "woman_astronaut" => "👩‍🚀", "firefighter" => "🧑‍🚒", "man_firefighter" => "👨‍🚒", "woman_firefighter" => "👩‍🚒",
            "police_officer" => "👮", "man_police_officer" => "👮‍♂️", "woman_police_officer" => "👮‍♀️", "detective" => "🕵️",
            "man_detective" => "🕵️‍♂️", "woman_detective" => "🕵️‍♀️", "guard" => "💂", "man_guard" => "💂‍♂️",
            "woman_guard" => "💂‍♀️", "ninja" => "🥷", "construction_worker" => "👷", "man_construction_worker" => "👷‍♂️",
            "woman_construction_worker" => "👷‍♀️", "person_with_crown" => "🫅", "prince" => "🤴", "princess" => "👸",
            "person_wearing_turban" => "👳", "man_wearing_turban" => "👳‍♂️", "woman_wearing_turban" => "👳‍♀️", "person_with_skullcap" => "👲",
            "woman_with_headscarf" => "🧕", "person_in_tuxedo" => "🤵", "man_in_tuxedo" => "🤵‍♂️", "woman_in_tuxedo" => "🤵‍♀️",
            "person_with_veil" => "👰", "man_with_veil" => "👰‍♂️", "woman_with_veil" => "👰‍♀️", "pregnant_woman" => "🤰",
            "pregnant_man" => "🫃", "pregnant_person" => "🫄", "breast_feeding" => "🤱", "woman_feeding_baby" => "👩‍🍼",
            "man_feeding_baby" => "👨‍🍼", "person_feeding_baby" => "🧑‍🍼", "baby_angel" => "👼", "santa_claus" => "🎅",
            "mrs_claus" => "🤶", "mx_claus" => "🧑‍🎄", "superhero" => "🦸", "man_superhero" => "🦸‍♂️",
            "woman_superhero" => "🦸‍♀️", "supervillain" => "🦹", "man_supervillain" => "🦹‍♂️", "woman_supervillain" => "🦹‍♀️",
            "mage" => "🧙", "man_mage" => "🧙‍♂️", "woman_mage" => "🧙‍♀️", "fairy" => "🧚",
            "man_fairy" => "🧚‍♂️", "woman_fairy" => "🧚‍♀️", "vampire" => "🧛", "man_vampire" => "🧛‍♂️",
            "woman_vampire" => "🧛‍♀️", "merperson" => "🧜", "merman" => "🧜‍♂️", "mermaid" => "🧜‍♀️",
            "elf" => "🧝", "man_elf" => "🧝‍♂️", "woman_elf" => "🧝‍♀️", "genie" => "🧞",
            "man_genie" => "🧞‍♂️", "woman_genie" => "🧞‍♀️", "zombie" => "🧟", "man_zombie" => "🧟‍♂️",
            "woman_zombie" => "🧟‍♀️", "troll" => "🧌", "person_getting_massage" => "💆", "man_getting_massage" => "💆‍♂️",
            "woman_getting_massage" => "💆‍♀️", "person_getting_haircut" => "💇", "man_getting_haircut" => "💇‍♂️", "woman_getting_haircut" => "💇‍♀️",
            "person_walking" => "🚶", "man_walking" => "🚶‍♂️", "woman_walking" => "🚶‍♀️", "person_walking_facing_right" => "🚶‍➡️",
            "woman_walking_facing_right" => "🚶‍♀️‍➡️", "man_walking_facing_right" => "🚶‍♂️‍➡️", "person_standing" => "🧍", "man_standing" => "🧍‍♂️",
            "woman_standing" => "🧍‍♀️", "person_kneeling" => "🧎", "man_kneeling" => "🧎‍♂️", "woman_kneeling" => "🧎‍♀️",
            "person_kneeling_facing_right" => "🧎‍➡️", "woman_kneeling_facing_right" => "🧎‍♀️‍➡️", "man_kneeling_facing_right" => "🧎‍♂️‍➡️", "person_with_white_cane" => "🧑‍🦯",
            "person_with_white_cane_facing_right" => "🧑‍🦯‍➡️", "man_with_white_cane" => "👨‍🦯", "man_with_white_cane_facing_right" => "👨‍🦯‍➡️", "woman_with_white_cane" => "👩‍🦯",
            "woman_with_white_cane_facing_right" => "👩‍🦯‍➡️", "person_in_motorized_wheelchair" => "🧑‍🦼", "person_in_motorized_wheelchair_facing_right" => "🧑‍🦼‍➡️", "man_in_motorized_wheelchair" => "👨‍🦼",
            "man_in_motorized_wheelchair_facing_right" => "👨‍🦼‍➡️", "woman_in_motorized_wheelchair" => "👩‍🦼", "woman_in_motorized_wheelchair_facing_right" => "👩‍🦼‍➡️", "person_in_manual_wheelchair" => "🧑‍🦽",
            "person_in_manual_wheelchair_facing_right" => "🧑‍🦽‍➡️", "man_in_manual_wheelchair" => "👨‍🦽", "man_in_manual_wheelchair_facing_right" => "👨‍🦽‍➡️", "woman_in_manual_wheelchair" => "👩‍🦽",
            "woman_in_manual_wheelchair_facing_right" => "👩‍🦽‍➡️", "person_running" => "🏃", "man_running" => "🏃‍♂️", "woman_running" => "🏃‍♀️",
            "person_running_facing_right" => "🏃‍➡️", "woman_running_facing_right" => "🏃‍♀️‍➡️", "man_running_facing_right" => "🏃‍♂️‍➡️", "woman_dancing" => "💃",
            "man_dancing" => "🕺", "person_in_suit_levitating" => "🕴️", "people_with_bunny_ears" => "👯", "men_with_bunny_ears" => "👯‍♂️",
            "women_with_bunny_ears" => "👯‍♀️", "person_in_steamy_room" => "🧖", "man_in_steamy_room" => "🧖‍♂️", "woman_in_steamy_room" => "🧖‍♀️",
            "person_climbing" => "🧗", "man_climbing" => "🧗‍♂️", "woman_climbing" => "🧗‍♀️", "person_fencing" => "🤺",
            "horse_racing" => "🏇", "skier" => "⛷️", "snowboarder" => "🏂", "person_golfing" => "🏌️",
            "man_golfing" => "🏌️‍♂️", "woman_golfing" => "🏌️‍♀️", "person_surfing" => "🏄", "man_surfing" => "🏄‍♂️",
            "woman_surfing" => "🏄‍♀️", "person_rowing_boat" => "🚣", "man_rowing_boat" => "🚣‍♂️", "woman_rowing_boat" => "🚣‍♀️",
            "person_swimming" => "🏊", "man_swimming" => "🏊‍♂️", "woman_swimming" => "🏊‍♀️", "person_bouncing_ball" => "⛹️",
            "man_bouncing_ball" => "⛹️‍♂️", "woman_bouncing_ball" => "⛹️‍♀️", "person_lifting_weights" => "🏋️", "man_lifting_weights" => "🏋️‍♂️",
            "woman_lifting_weights" => "🏋️‍♀️", "person_biking" => "🚴", "man_biking" => "🚴‍♂️", "woman_biking" => "🚴‍♀️",
            "person_mountain_biking" => "🚵", "man_mountain_biking" => "🚵‍♂️", "woman_mountain_biking" => "🚵‍♀️", "person_cartwheeling" => "🤸",
            "man_cartwheeling" => "🤸‍♂️", "woman_cartwheeling" => "🤸‍♀️", "people_wrestling" => "🤼", "men_wrestling" => "🤼‍♂️",
            "women_wrestling" => "🤼‍♀️", "person_playing_water_polo" => "🤽", "man_playing_water_polo" => "🤽‍♂️", "woman_playing_water_polo" => "🤽‍♀️",
            "person_playing_handball" => "🤾", "man_playing_handball" => "🤾‍♂️", "woman_playing_handball" => "🤾‍♀️", "person_juggling" => "🤹",
            "man_juggling" => "🤹‍♂️", "woman_juggling" => "🤹‍♀️", "person_in_lotus_position" => "🧘", "man_in_lotus_position" => "🧘‍♂️",
            "woman_in_lotus_position" => "🧘‍♀️", "person_taking_bath" => "🛀", "person_in_bed" => "🛌", "people_holding_hands" => "🧑‍🤝‍🧑",
            "women_holding_hands" => "👭", "woman_and_man_holding_hands" => "👫", "men_holding_hands" => "👬", "kiss" => "💏",
            "kiss_woman_man" => "👩‍❤️‍💋‍👨", "kiss_man_man" => "👨‍❤️‍💋‍👨", "kiss_woman_woman" => "👩‍❤️‍💋‍👩", "couple_with_heart" => "💑",
            "couple_with_heart_woman_man" => "👩‍❤️‍👨", "couple_with_heart_man_man" => "👨‍❤️‍👨", "couple_with_heart_woman_woman" => "👩‍❤️‍👩", "family_man_woman_boy" => "👨‍👩‍👦",
            "family_man_woman_girl" => "👨‍👩‍👧", "family_man_woman_girl_boy" => "👨‍👩‍👧‍👦", "family_man_woman_boy_boy" => "👨‍👩‍👦‍👦", "family_man_woman_girl_girl" => "👨‍👩‍👧‍👧",
            "family_man_man_boy" => "👨‍👨‍👦", "family_man_man_girl" => "👨‍👨‍👧", "family_man_man_girl_boy" => "👨‍👨‍👧‍👦", "family_man_man_boy_boy" => "👨‍👨‍👦‍👦",
            "family_man_man_girl_girl" => "👨‍👨‍👧‍👧", "family_woman_woman_boy" => "👩‍👩‍👦", "family_woman_woman_girl" => "👩‍👩‍👧", "family_woman_woman_girl_boy" => "👩‍👩‍👧‍👦",
            "family_woman_woman_boy_boy" => "👩‍👩‍👦‍👦", "family_woman_woman_girl_girl" => "👩‍👩‍👧‍👧", "family_man_boy" => "👨‍👦", "family_man_boy_boy" => "👨‍👦‍👦",
            "family_man_girl" => "👨‍👧", "family_man_girl_boy" => "👨‍👧‍👦", "family_man_girl_girl" => "👨‍👧‍👧", "family_woman_boy" => "👩‍👦",
            "family_woman_boy_boy" => "👩‍👦‍👦", "family_woman_girl" => "👩‍👧", "family_woman_girl_boy" => "👩‍👧‍👦", "family_woman_girl_girl" => "👩‍👧‍👧",
            "speaking_head" => "🗣️", "bust_in_silhouette" => "👤", "busts_in_silhouette" => "👥", "people_hugging" => "🫂",
            "family" => "👪", "family_adult_adult_child" => "🧑‍🧑‍🧒", "family_adult_adult_child_child" => "🧑‍🧑‍🧒‍🧒", "family_adult_child" => "🧑‍🧒",
            "family_adult_child_child" => "🧑‍🧒‍🧒", "footprints" => "👣", "fingerprint" => "🫆", "monkey_face" => "🐵",
            "monkey" => "🐒", "gorilla" => "🦍", "orangutan" => "🦧", "dog_face" => "🐶",
            "dog" => "🐕", "guide_dog" => "🦮", "service_dog" => "🐕‍🦺", "poodle" => "🐩",
            "wolf" => "🐺", "fox" => "🦊", "raccoon" => "🦝", "cat_face" => "🐱",
            "cat" => "🐈", "black_cat" => "🐈‍⬛", "lion" => "🦁", "tiger_face" => "🐯",
            "tiger" => "🐅", "leopard" => "🐆", "horse_face" => "🐴", "moose" => "🫎",
            "donkey" => "🫏", "horse" => "🐎", "unicorn" => "🦄", "zebra" => "🦓",
            "deer" => "🦌", "bison" => "🦬", "cow_face" => "🐮", "ox" => "🐂",
            "water_buffalo" => "🐃", "cow" => "🐄", "pig_face" => "🐷", "pig" => "🐖",
            "boar" => "🐗", "pig_nose" => "🐽", "ram" => "🐏", "ewe" => "🐑",
            "goat" => "🐐", "camel" => "🐪", "two_hump_camel" => "🐫", "llama" => "🦙",
            "giraffe" => "🦒", "elephant" => "🐘", "mammoth" => "🦣", "rhinoceros" => "🦏",
            "hippopotamus" => "🦛", "mouse_face" => "🐭", "mouse" => "🐁", "rat" => "🐀",
            "hamster" => "🐹", "rabbit_face" => "🐰", "rabbit" => "🐇", "chipmunk" => "🐿️",
            "beaver" => "🦫", "hedgehog" => "🦔", "bat" => "🦇", "bear" => "🐻",
            "polar_bear" => "🐻‍❄️", "koala" => "🐨", "panda" => "🐼", "sloth" => "🦥",
            "otter" => "🦦", "skunk" => "🦨", "kangaroo" => "🦘", "badger" => "🦡",
            "paw_prints" => "🐾", "turkey" => "🦃", "chicken" => "🐔", "rooster" => "🐓",
            "hatching_chick" => "🐣", "baby_chick" => "🐤", "front_facing_baby_chick" => "🐥", "bird" => "🐦",
            "penguin" => "🐧", "dove" => "🕊️", "eagle" => "🦅", "duck" => "🦆",
            "swan" => "🦢", "owl" => "🦉", "dodo" => "🦤", "feather" => "🪶",
            "flamingo" => "🦩", "peacock" => "🦚", "parrot" => "🦜", "wing" => "🪽",
            "black_bird" => "🐦‍⬛", "goose" => "🪿", "phoenix" => "🐦‍🔥", "frog" => "🐸",
            "crocodile" => "🐊", "turtle" => "🐢", "lizard" => "🦎", "snake" => "🐍",
            "dragon_face" => "🐲", "dragon" => "🐉", "sauropod" => "🦕", "t_rex" => "🦖",
            "spouting_whale" => "🐳", "whale" => "🐋", "dolphin" => "🐬", "seal" => "🦭",
            "fish" => "🐟", "tropical_fish" => "🐠", "blowfish" => "🐡", "shark" => "🦈",
            "octopus" => "🐙", "spiral_shell" => "🐚", "coral" => "🪸", "jellyfish" => "🪼",
            "crab" => "🦀", "lobster" => "🦞", "shrimp" => "🦐", "squid" => "🦑",
            "oyster" => "🦪", "snail" => "🐌", "butterfly" => "🦋", "bug" => "🐛",
            "ant" => "🐜", "honeybee" => "🐝", "beetle" => "🪲", "lady_beetle" => "🐞",
            "cricket" => "🦗", "cockroach" => "🪳", "spider" => "🕷️", "spider_web" => "🕸️",
            "scorpion" => "🦂", "mosquito" => "🦟", "fly" => "🪰", "worm" => "🪱",
            "microbe" => "🦠", "bouquet" => "💐", "cherry_blossom" => "🌸", "white_flower" => "💮",
            "lotus" => "🪷", "rosette" => "🏵️", "rose" => "🌹", "wilted_flower" => "🥀",
            "hibiscus" => "🌺", "sunflower" => "🌻", "blossom" => "🌼", "tulip" => "🌷",
            "hyacinth" => "🪻", "seedling" => "🌱", "potted_plant" => "🪴", "evergreen_tree" => "🌲",
            "deciduous_tree" => "🌳", "palm_tree" => "🌴", "cactus" => "🌵", "sheaf_of_rice" => "🌾",
            "herb" => "🌿", "shamrock" => "☘️", "four_leaf_clover" => "🍀", "maple_leaf" => "🍁",
            "fallen_leaf" => "🍂", "leaf_fluttering_in_wind" => "🍃", "empty_nest" => "🪹", "nest_with_eggs" => "🪺",
            "mushroom" => "🍄", "leafless_tree" => "🪾", "grapes" => "🍇", "melon" => "🍈",
            "watermelon" => "🍉", "tangerine" => "🍊", "lemon" => "🍋", "lime" => "🍋‍🟩",
            "banana" => "🍌", "pineapple" => "🍍", "mango" => "🥭", "red_apple" => "🍎",
            "green_apple" => "🍏", "pear" => "🍐", "peach" => "🍑", "cherries" => "🍒",
            "strawberry" => "🍓", "blueberries" => "🫐", "kiwi_fruit" => "🥝", "tomato" => "🍅",
            "olive" => "🫒", "coconut" => "🥥", "avocado" => "🥑", "eggplant" => "🍆",
            "potato" => "🥔", "carrot" => "🥕", "ear_of_corn" => "🌽", "hot_pepper" => "🌶️",
            "bell_pepper" => "🫑", "cucumber" => "🥒", "leafy_green" => "🥬", "broccoli" => "🥦",
            "garlic" => "🧄", "onion" => "🧅", "peanuts" => "🥜", "beans" => "🫘",
            "chestnut" => "🌰", "ginger_root" => "🫚", "pea_pod" => "🫛", "brown_mushroom" => "🍄‍🟫",
            "root_vegetable" => "🫜", "bread" => "🍞", "croissant" => "🥐", "baguette_bread" => "🥖",
            "flatbread" => "🫓", "pretzel" => "🥨", "bagel" => "🥯", "pancakes" => "🥞",
            "waffle" => "🧇", "cheese_wedge" => "🧀", "meat_on_bone" => "🍖", "poultry_leg" => "🍗",
            "cut_of_meat" => "🥩", "bacon" => "🥓", "hamburger" => "🍔", "french_fries" => "🍟",
            "pizza" => "🍕", "hot_dog" => "🌭", "sandwich" => "🥪", "taco" => "🌮",
            "burrito" => "🌯", "tamale" => "🫔", "stuffed_flatbread" => "🥙", "falafel" => "🧆",
            "egg" => "🥚", "cooking" => "🍳", "shallow_pan_of_food" => "🥘", "pot_of_food" => "🍲",
            "fondue" => "🫕", "bowl_with_spoon" => "🥣", "green_salad" => "🥗", "popcorn" => "🍿",
            "butter" => "🧈", "salt" => "🧂", "canned_food" => "🥫", "bento_box" => "🍱",
            "rice_cracker" => "🍘", "rice_ball" => "🍙", "cooked_rice" => "🍚", "curry_rice" => "🍛",
            "steaming_bowl" => "🍜", "spaghetti" => "🍝", "roasted_sweet_potato" => "🍠", "oden" => "🍢",
            "sushi" => "🍣", "fried_shrimp" => "🍤", "fish_cake_with_swirl" => "🍥", "moon_cake" => "🥮",
            "dango" => "🍡", "dumpling" => "🥟", "fortune_cookie" => "🥠", "takeout_box" => "🥡",
            "soft_ice_cream" => "🍦", "shaved_ice" => "🍧", "ice_cream" => "🍨", "doughnut" => "🍩",
            "cookie" => "🍪", "birthday_cake" => "🎂", "shortcake" => "🍰", "cupcake" => "🧁",
            "pie" => "🥧", "chocolate_bar" => "🍫", "candy" => "🍬", "lollipop" => "🍭",
            "custard" => "🍮", "honey_pot" => "🍯", "baby_bottle" => "🍼", "glass_of_milk" => "🥛",
            "hot_beverage" => "☕", "teapot" => "🫖", "teacup_without_handle" => "🍵", "sake" => "🍶",
            "bottle_with_popping_cork" => "🍾", "wine_glass" => "🍷", "cocktail_glass" => "🍸", "tropical_drink" => "🍹",
            "beer_mug" => "🍺", "clinking_beer_mugs" => "🍻", "clinking_glasses" => "🥂", "tumbler_glass" => "🥃",
            "pouring_liquid" => "🫗", "cup_with_straw" => "🥤", "bubble_tea" => "🧋", "beverage_box" => "🧃",
            "mate" => "🧉", "ice" => "🧊", "chopsticks" => "🥢", "fork_and_knife_with_plate" => "🍽️",
            "fork_and_knife" => "🍴", "spoon" => "🥄", "kitchen_knife" => "🔪", "jar" => "🫙",
            "amphora" => "🏺", "globe_showing_europe_africa" => "🌍", "globe_showing_americas" => "🌎", "globe_showing_asia_australia" => "🌏",
            "globe_with_meridians" => "🌐", "world_map" => "🗺️", "map_of_japan" => "🗾", "compass" => "🧭",
            "snow_capped_mountain" => "🏔️", "mountain" => "⛰️", "volcano" => "🌋", "mount_fuji" => "🗻",
            "camping" => "🏕️", "beach_with_umbrella" => "🏖️", "desert" => "🏜️", "desert_island" => "🏝️",
            "national_park" => "🏞️", "stadium" => "🏟️", "classical_building" => "🏛️", "building_construction" => "🏗️",
            "brick" => "🧱", "rock" => "🪨", "wood" => "🪵", "hut" => "🛖",
            "houses" => "🏘️", "derelict_house" => "🏚️", "house" => "🏠", "house_with_garden" => "🏡",
            "office_building" => "🏢", "japanese_post_office" => "🏣", "post_office" => "🏤", "hospital" => "🏥",
            "bank" => "🏦", "hotel" => "🏨", "love_hotel" => "🏩", "convenience_store" => "🏪",
            "school" => "🏫", "department_store" => "🏬", "factory" => "🏭", "japanese_castle" => "🏯",
            "castle" => "🏰", "wedding" => "💒", "tokyo_tower" => "🗼", "statue_of_liberty" => "🗽",
            "church" => "⛪", "mosque" => "🕌", "hindu_temple" => "🛕", "synagogue" => "🕍",
            "shinto_shrine" => "⛩️", "kaaba" => "🕋", "fountain" => "⛲", "tent" => "⛺",
            "foggy" => "🌁", "night_with_stars" => "🌃", "cityscape" => "🏙️", "sunrise_over_mountains" => "🌄",
            "sunrise" => "🌅", "cityscape_at_dusk" => "🌆", "sunset" => "🌇", "bridge_at_night" => "🌉",
            "hot_springs" => "♨️", "carousel_horse" => "🎠", "playground_slide" => "🛝", "ferris_wheel" => "🎡",
            "roller_coaster" => "🎢", "barber_pole" => "💈", "circus_tent" => "🎪", "locomotive" => "🚂",
            "railway_car" => "🚃", "high_speed_train" => "🚄", "bullet_train" => "🚅", "train" => "🚆",
            "metro" => "🚇", "light_rail" => "🚈", "station" => "🚉", "tram" => "🚊",
            "monorail" => "🚝", "mountain_railway" => "🚞", "tram_car" => "🚋", "bus" => "🚌",
            "oncoming_bus" => "🚍", "trolleybus" => "🚎", "minibus" => "🚐", "ambulance" => "🚑",
            "fire_engine" => "🚒", "police_car" => "🚓", "oncoming_police_car" => "🚔", "taxi" => "🚕",
            "oncoming_taxi" => "🚖", "automobile" => "🚗", "oncoming_automobile" => "🚘", "sport_utility_vehicle" => "🚙",
            "pickup_truck" => "🛻", "delivery_truck" => "🚚", "articulated_lorry" => "🚛", "tractor" => "🚜",
            "racing_car" => "🏎️", "motorcycle" => "🏍️", "motor_scooter" => "🛵", "manual_wheelchair" => "🦽",
            "motorized_wheelchair" => "🦼", "auto_rickshaw" => "🛺", "bicycle" => "🚲", "kick_scooter" => "🛴",
            "skateboard" => "🛹", "roller_skate" => "🛼", "bus_stop" => "🚏", "motorway" => "🛣️",
            "railway_track" => "🛤️", "oil_drum" => "🛢️", "fuel_pump" => "⛽", "wheel" => "🛞",
            "police_car_light" => "🚨", "horizontal_traffic_light" => "🚥", "vertical_traffic_light" => "🚦", "stop_sign" => "🛑",
            "construction" => "🚧", "anchor" => "⚓", "ring_buoy" => "🛟", "sailboat" => "⛵",
            "canoe" => "🛶", "speedboat" => "🚤", "passenger_ship" => "🛳️", "ferry" => "⛴️",
            "motor_boat" => "🛥️", "ship" => "🚢", "airplane" => "✈️", "small_airplane" => "🛩️",
            "airplane_departure" => "🛫", "airplane_arrival" => "🛬", "parachute" => "🪂", "seat" => "💺",
            "helicopter" => "🚁", "suspension_railway" => "🚟", "mountain_cableway" => "🚠", "aerial_tramway" => "🚡",
            "satellite" => "🛰️", "rocket" => "🚀", "flying_saucer" => "🛸", "bellhop_bell" => "🛎️",
            "luggage" => "🧳", "hourglass_done" => "⌛", "hourglass_not_done" => "⏳", "watch" => "⌚",
            "alarm_clock" => "⏰", "stopwatch" => "⏱️", "timer_clock" => "⏲️", "mantelpiece_clock" => "🕰️",
            "twelve_o_clock" => "🕛", "twelve_thirty" => "🕧", "one_o_clock" => "🕐", "one_thirty" => "🕜",
            "two_o_clock" => "🕑", "two_thirty" => "🕝", "three_o_clock" => "🕒", "three_thirty" => "🕞",
            "four_o_clock" => "🕓", "four_thirty" => "🕟", "five_o_clock" => "🕔", "five_thirty" => "🕠",
            "six_o_clock" => "🕕", "six_thirty" => "🕡", "seven_o_clock" => "🕖", "seven_thirty" => "🕢",
            "eight_o_clock" => "🕗", "eight_thirty" => "🕣", "nine_o_clock" => "🕘", "nine_thirty" => "🕤",
            "ten_o_clock" => "🕙", "ten_thirty" => "🕥", "eleven_o_clock" => "🕚", "eleven_thirty" => "🕦",
            "new_moon" => "🌑", "waxing_crescent_moon" => "🌒", "first_quarter_moon" => "🌓", "waxing_gibbous_moon" => "🌔",
            "full_moon" => "🌕", "waning_gibbous_moon" => "🌖", "last_quarter_moon" => "🌗", "waning_crescent_moon" => "🌘",
            "crescent_moon" => "🌙", "new_moon_face" => "🌚", "first_quarter_moon_face" => "🌛", "last_quarter_moon_face" => "🌜",
            "thermometer" => "🌡️", "sun" => "☀️", "full_moon_face" => "🌝", "sun_with_face" => "🌞",
            "ringed_planet" => "🪐", "star" => "⭐", "glowing_star" => "🌟", "shooting_star" => "🌠",
            "milky_way" => "🌌", "cloud" => "☁️", "sun_behind_cloud" => "⛅", "cloud_with_lightning_and_rain" => "⛈️",
            "sun_behind_small_cloud" => "🌤️", "sun_behind_large_cloud" => "🌥️", "sun_behind_rain_cloud" => "🌦️", "cloud_with_rain" => "🌧️",
            "cloud_with_snow" => "🌨️", "cloud_with_lightning" => "🌩️", "tornado" => "🌪️", "fog" => "🌫️",
            "wind_face" => "🌬️", "cyclone" => "🌀", "rainbow" => "🌈", "closed_umbrella" => "🌂",
            "umbrella" => "☂️", "umbrella_with_rain_drops" => "☔", "umbrella_on_ground" => "⛱️", "high_voltage" => "⚡",
            "snowflake" => "❄️", "snowman" => "☃️", "snowman_without_snow" => "⛄", "comet" => "☄️",
            "fire" => "🔥", "droplet" => "💧", "water_wave" => "🌊", "jack_o_lantern" => "🎃",
            "christmas_tree" => "🎄", "fireworks" => "🎆", "sparkler" => "🎇", "firecracker" => "🧨",
            "sparkles" => "✨", "balloon" => "🎈", "party_popper" => "🎉", "confetti_ball" => "🎊",
            "tanabata_tree" => "🎋", "pine_decoration" => "🎍", "japanese_dolls" => "🎎", "carp_streamer" => "🎏",
            "wind_chime" => "🎐", "moon_viewing_ceremony" => "🎑", "red_envelope" => "🧧", "ribbon" => "🎀",
            "wrapped_gift" => "🎁", "reminder_ribbon" => "🎗️", "admission_tickets" => "🎟️", "ticket" => "🎫",
            "military_medal" => "🎖️", "trophy" => "🏆", "sports_medal" => "🏅", "1st_place_medal" => "🥇",
            "2nd_place_medal" => "🥈", "3rd_place_medal" => "🥉", "soccer_ball" => "⚽", "baseball" => "⚾",
            "softball" => "🥎", "basketball" => "🏀", "volleyball" => "🏐", "american_football" => "🏈",
            "rugby_football" => "🏉", "tennis" => "🎾", "flying_disc" => "🥏", "bowling" => "🎳",
            "cricket_game" => "🏏", "field_hockey" => "🏑", "ice_hockey" => "🏒", "lacrosse" => "🥍",
            "ping_pong" => "🏓", "badminton" => "🏸", "boxing_glove" => "🥊", "martial_arts_uniform" => "🥋",
            "goal_net" => "🥅", "flag_in_hole" => "⛳", "ice_skate" => "⛸️", "fishing_pole" => "🎣",
            "diving_mask" => "🤿", "running_shirt" => "🎽", "skis" => "🎿", "sled" => "🛷",
            "curling_stone" => "🥌", "bullseye" => "🎯", "yo_yo" => "🪀", "kite" => "🪁",
            "water_pistol" => "🔫", "pool_8_ball" => "🎱", "crystal_ball" => "🔮", "magic_wand" => "🪄",
            "video_game" => "🎮", "joystick" => "🕹️", "slot_machine" => "🎰", "game_die" => "🎲",
            "puzzle_piece" => "🧩", "teddy_bear" => "🧸", "pinata" => "🪅", "mirror_ball" => "🪩",
            "nesting_dolls" => "🪆", "spade_suit" => "♠️", "heart_suit" => "♥️", "diamond_suit" => "♦️",
            "club_suit" => "♣️", "chess_pawn" => "♟️", "joker" => "🃏", "mahjong_red_dragon" => "🀄",
            "flower_playing_cards" => "🎴", "performing_arts" => "🎭", "framed_picture" => "🖼️", "artist_palette" => "🎨",
            "thread" => "🧵", "sewing_needle" => "🪡", "yarn" => "🧶", "knot" => "🪢",
            "glasses" => "👓", "sunglasses" => "🕶️", "goggles" => "🥽", "lab_coat" => "🥼",
            "safety_vest" => "🦺", "necktie" => "👔", "t_shirt" => "👕", "jeans" => "👖",
            "scarf" => "🧣", "gloves" => "🧤", "coat" => "🧥", "socks" => "🧦",
            "dress" => "👗", "kimono" => "👘", "sari" => "🥻", "one_piece_swimsuit" => "🩱",
            "briefs" => "🩲", "shorts" => "🩳", "bikini" => "👙", "woman_s_clothes" => "👚",
            "folding_hand_fan" => "🪭", "purse" => "👛", "handbag" => "👜", "clutch_bag" => "👝",
            "shopping_bags" => "🛍️", "backpack" => "🎒", "thong_sandal" => "🩴", "man_s_shoe" => "👞",
            "running_shoe" => "👟", "hiking_boot" => "🥾", "flat_shoe" => "🥿", "high_heeled_shoe" => "👠",
            "woman_s_sandal" => "👡", "ballet_shoes" => "🩰", "woman_s_boot" => "👢", "hair_pick" => "🪮",
            "crown" => "👑", "woman_s_hat" => "👒", "top_hat" => "🎩", "graduation_cap" => "🎓",
            "billed_cap" => "🧢", "military_helmet" => "🪖", "rescue_worker_s_helmet" => "⛑️", "prayer_beads" => "📿",
            "lipstick" => "💄", "ring" => "💍", "gem_stone" => "💎", "muted_speaker" => "🔇",
            "speaker_low_volume" => "🔈", "speaker_medium_volume" => "🔉", "speaker_high_volume" => "🔊", "loudspeaker" => "📢",
            "megaphone" => "📣", "postal_horn" => "📯", "bell" => "🔔", "bell_with_slash" => "🔕",
            "musical_score" => "🎼", "musical_note" => "🎵", "musical_notes" => "🎶", "studio_microphone" => "🎙️",
            "level_slider" => "🎚️", "control_knobs" => "🎛️", "microphone" => "🎤", "headphone" => "🎧",
            "radio" => "📻", "saxophone" => "🎷", "accordion" => "🪗", "guitar" => "🎸",
            "musical_keyboard" => "🎹", "trumpet" => "🎺", "violin" => "🎻", "banjo" => "🪕",
            "drum" => "🥁", "long_drum" => "🪘", "maracas" => "🪇", "flute" => "🪈",
            "harp" => "🪉", "mobile_phone" => "📱", "mobile_phone_with_arrow" => "📲", "telephone" => "☎️",
            "telephone_receiver" => "📞", "pager" => "📟", "fax_machine" => "📠", "battery" => "🔋",
            "low_battery" => "🪫", "electric_plug" => "🔌", "laptop" => "💻", "desktop_computer" => "🖥️",
            "printer" => "🖨️", "keyboard" => "⌨️", "computer_mouse" => "🖱️", "trackball" => "🖲️",
            "computer_disk" => "💽", "floppy_disk" => "💾", "optical_disk" => "💿", "dvd" => "📀",
            "abacus" => "🧮", "movie_camera" => "🎥", "film_frames" => "🎞️", "film_projector" => "📽️",
            "clapper_board" => "🎬", "television" => "📺", "camera" => "📷", "camera_with_flash" => "📸",
            "video_camera" => "📹", "videocassette" => "📼", "magnifying_glass_tilted_left" => "🔍", "magnifying_glass_tilted_right" => "🔎",
            "candle" => "🕯️", "light_bulb" => "💡", "flashlight" => "🔦", "red_paper_lantern" => "🏮",
            "diya_lamp" => "🪔", "notebook_with_decorative_cover" => "📔", "closed_book" => "📕", "open_book" => "📖",
            "green_book" => "📗", "blue_book" => "📘", "orange_book" => "📙", "books" => "📚",
            "notebook" => "📓", "ledger" => "📒", "page_with_curl" => "📃", "scroll" => "📜",
            "page_facing_up" => "📄", "newspaper" => "📰", "rolled_up_newspaper" => "🗞️", "bookmark_tabs" => "📑",
            "bookmark" => "🔖", "label" => "🏷️", "money_bag" => "💰", "coin" => "🪙",
            "yen_banknote" => "💴", "dollar_banknote" => "💵", "euro_banknote" => "💶", "pound_banknote" => "💷",
            "money_with_wings" => "💸", "credit_card" => "💳", "receipt" => "🧾", "chart_increasing_with_yen" => "💹",
            "envelope" => "✉️", "e_mail" => "📧", "incoming_envelope" => "📨", "envelope_with_arrow" => "📩",
            "outbox_tray" => "📤", "inbox_tray" => "📥", "package" => "📦", "closed_mailbox_with_raised_flag" => "📫",
            "closed_mailbox_with_lowered_flag" => "📪", "open_mailbox_with_raised_flag" => "📬", "open_mailbox_with_lowered_flag" => "📭", "postbox" => "📮",
            "ballot_box_with_ballot" => "🗳️", "pencil" => "✏️", "black_nib" => "✒️", "fountain_pen" => "🖋️",
            "pen" => "🖊️", "paintbrush" => "🖌️", "crayon" => "🖍️", "memo" => "📝",
            "briefcase" => "💼", "file_folder" => "📁", "open_file_folder" => "📂", "card_index_dividers" => "🗂️",
            "calendar" => "📅", "tear_off_calendar" => "📆", "spiral_notepad" => "🗒️", "spiral_calendar" => "🗓️",
            "card_index" => "📇", "chart_increasing" => "📈", "chart_decreasing" => "📉", "bar_chart" => "📊",
            "clipboard" => "📋", "pushpin" => "📌", "round_pushpin" => "📍", "paperclip" => "📎",
            "linked_paperclips" => "🖇️", "straight_ruler" => "📏", "triangular_ruler" => "📐", "scissors" => "✂️",
            "card_file_box" => "🗃️", "file_cabinet" => "🗄️", "wastebasket" => "🗑️", "locked" => "🔒",
            "unlocked" => "🔓", "locked_with_pen" => "🔏", "locked_with_key" => "🔐", "key" => "🔑",
            "old_key" => "🗝️", "hammer" => "🔨", "axe" => "🪓", "pick" => "⛏️",
            "hammer_and_pick" => "⚒️", "hammer_and_wrench" => "🛠️", "dagger" => "🗡️", "crossed_swords" => "⚔️",
            "bomb" => "💣", "boomerang" => "🪃", "bow_and_arrow" => "🏹", "shield" => "🛡️",
            "carpentry_saw" => "🪚", "wrench" => "🔧", "screwdriver" => "🪛", "nut_and_bolt" => "🔩",
            "gear" => "⚙️", "clamp" => "🗜️", "balance_scale" => "⚖️", "white_cane" => "🦯",
            "link" => "🔗", "broken_chain" => "⛓️‍💥", "chains" => "⛓️", "hook" => "🪝",
            "toolbox" => "🧰", "magnet" => "🧲", "ladder" => "🪜", "shovel" => "🪏",
            "alembic" => "⚗️", "test_tube" => "🧪", "petri_dish" => "🧫", "dna" => "🧬",
            "microscope" => "🔬", "telescope" => "🔭", "satellite_antenna" => "📡", "syringe" => "💉",
            "drop_of_blood" => "🩸", "pill" => "💊", "adhesive_bandage" => "🩹", "crutch" => "🩼",
            "stethoscope" => "🩺", "x_ray" => "🩻", "door" => "🚪", "elevator" => "🛗",
            "mirror" => "🪞", "window" => "🪟", "bed" => "🛏️", "couch_and_lamp" => "🛋️",
            "chair" => "🪑", "toilet" => "🚽", "plunger" => "🪠", "shower" => "🚿",
            "bathtub" => "🛁", "mouse_trap" => "🪤", "razor" => "🪒", "lotion_bottle" => "🧴",
            "safety_pin" => "🧷", "broom" => "🧹", "basket" => "🧺", "roll_of_paper" => "🧻",
            "bucket" => "🪣", "soap" => "🧼", "bubbles" => "🫧", "toothbrush" => "🪥",
            "sponge" => "🧽", "fire_extinguisher" => "🧯", "shopping_cart" => "🛒", "cigarette" => "🚬",
            "coffin" => "⚰️", "headstone" => "🪦", "funeral_urn" => "⚱️", "nazar_amulet" => "🧿",
            "hamsa" => "🪬", "moai" => "🗿", "placard" => "🪧", "identification_card" => "🪪",
            "atm_sign" => "🏧", "litter_in_bin_sign" => "🚮", "potable_water" => "🚰", "wheelchair_symbol" => "♿",
            "men_s_room" => "🚹", "women_s_room" => "🚺", "restroom" => "🚻", "baby_symbol" => "🚼",
            "water_closet" => "🚾", "passport_control" => "🛂", "customs" => "🛃", "baggage_claim" => "🛄",
            "left_luggage" => "🛅", "warning" => "⚠️", "children_crossing" => "🚸", "no_entry" => "⛔",
            "prohibited" => "🚫", "no_bicycles" => "🚳", "no_smoking" => "🚭", "no_littering" => "🚯",
            "non_potable_water" => "🚱", "no_pedestrians" => "🚷", "no_mobile_phones" => "📵", "no_one_under_eighteen" => "🔞",
            "radioactive" => "☢️", "biohazard" => "☣️", "up_arrow" => "⬆️", "up_right_arrow" => "↗️",
            "right_arrow" => "➡️", "down_right_arrow" => "↘️", "down_arrow" => "⬇️", "down_left_arrow" => "↙️",
            "left_arrow" => "⬅️", "up_left_arrow" => "↖️", "up_down_arrow" => "↕️", "left_right_arrow" => "↔️",
            "right_arrow_curving_left" => "↩️", "left_arrow_curving_right" => "↪️", "right_arrow_curving_up" => "⤴️", "right_arrow_curving_down" => "⤵️",
            "clockwise_vertical_arrows" => "🔃", "counterclockwise_arrows_button" => "🔄", "back_arrow" => "🔙", "end_arrow" => "🔚",
            "on_arrow" => "🔛", "soon_arrow" => "🔜", "top_arrow" => "🔝", "place_of_worship" => "🛐",
            "atom_symbol" => "⚛️", "om" => "🕉️", "star_of_david" => "✡️", "wheel_of_dharma" => "☸️",
            "yin_yang" => "☯️", "latin_cross" => "✝️", "orthodox_cross" => "☦️", "star_and_crescent" => "☪️",
            "peace_symbol" => "☮️", "menorah" => "🕎", "dotted_six_pointed_star" => "🔯", "khanda" => "🪯",
            "aries" => "♈", "taurus" => "♉", "gemini" => "♊", "cancer" => "♋",
            "leo" => "♌", "virgo" => "♍", "libra" => "♎", "scorpio" => "♏",
            "sagittarius" => "♐", "capricorn" => "♑", "aquarius" => "♒", "pisces" => "♓",
            "ophiuchus" => "⛎", "shuffle_tracks_button" => "🔀", "repeat_button" => "🔁", "repeat_single_button" => "🔂",
            "play_button" => "▶️", "fast_forward_button" => "⏩", "next_track_button" => "⏭️", "play_or_pause_button" => "⏯️",
            "reverse_button" => "◀️", "fast_reverse_button" => "⏪", "last_track_button" => "⏮️", "upwards_button" => "🔼",
            "fast_up_button" => "⏫", "downwards_button" => "🔽", "fast_down_button" => "⏬", "pause_button" => "⏸️",
            "stop_button" => "⏹️", "record_button" => "⏺️", "eject_button" => "⏏️", "cinema" => "🎦",
            "dim_button" => "🔅", "bright_button" => "🔆", "antenna_bars" => "📶", "wireless" => "🛜",
            "vibration_mode" => "📳", "mobile_phone_off" => "📴", "female_sign" => "♀️", "male_sign" => "♂️",
            "transgender_symbol" => "⚧️", "multiply" => "✖️", "plus" => "➕", "minus" => "➖",
            "divide" => "➗", "heavy_equals_sign" => "🟰", "infinity" => "♾️", "double_exclamation_mark" => "‼️",
            "exclamation_question_mark" => "⁉️", "red_question_mark" => "❓", "white_question_mark" => "❔", "white_exclamation_mark" => "❕",
            "red_exclamation_mark" => "❗", "wavy_dash" => "〰️", "currency_exchange" => "💱", "heavy_dollar_sign" => "💲",
            "medical_symbol" => "⚕️", "recycling_symbol" => "♻️", "fleur_de_lis" => "⚜️", "trident_emblem" => "🔱",
            "name_badge" => "📛", "japanese_symbol_for_beginner" => "🔰", "hollow_red_circle" => "⭕", "check_mark_button" => "✅",
            "check_box_with_check" => "☑️", "check_mark" => "✔️", "cross_mark" => "❌", "cross_mark_button" => "❎",
            "curly_loop" => "➰", "double_curly_loop" => "➿", "part_alternation_mark" => "〽️", "eight_spoked_asterisk" => "✳️",
            "eight_pointed_star" => "✴️", "sparkle" => "❇️", "copyright" => "©️", "registered" => "®️",
            "trade_mark" => "™️", "splatter" => "🫟", "keycap_number_sign" => "#️⃣", "keycap_asterisk" => "*️⃣",
            "keycap_0" => "0️⃣", "keycap_1" => "1️⃣", "keycap_2" => "2️⃣", "keycap_3" => "3️⃣",
            "keycap_4" => "4️⃣", "keycap_5" => "5️⃣", "keycap_6" => "6️⃣", "keycap_7" => "7️⃣",
            "keycap_8" => "8️⃣", "keycap_9" => "9️⃣", "keycap_10" => "🔟", "input_latin_uppercase" => "🔠",
            "input_latin_lowercase" => "🔡", "input_numbers" => "🔢", "input_symbols" => "🔣", "input_latin_letters" => "🔤",
            "a_button" => "🅰️", "ab_button" => "🆎", "b_button" => "🅱️", "cl_button" => "🆑",
            "cool_button" => "🆒", "free_button" => "🆓", "information" => "ℹ️", "id_button" => "🆔",
            "circled_m" => "Ⓜ️", "new_button" => "🆕", "ng_button" => "🆖", "o_button" => "🅾️",
            "ok_button" => "🆗", "p_button" => "🅿️", "sos_button" => "🆘", "up_button" => "🆙",
            "vs_button" => "🆚", "japanese_here_button" => "🈁", "japanese_service_charge_button" => "🈂️", "japanese_monthly_amount_button" => "🈷️",
            "japanese_not_free_of_charge_button" => "🈶", "japanese_reserved_button" => "🈯", "japanese_bargain_button" => "🉐", "japanese_discount_button" => "🈹",
            "japanese_free_of_charge_button" => "🈚", "japanese_prohibited_button" => "🈲", "japanese_acceptable_button" => "🉑", "japanese_application_button" => "🈸",
            "japanese_passing_grade_button" => "🈴", "japanese_vacancy_button" => "🈳", "japanese_congratulations_button" => "㊗️", "japanese_secret_button" => "㊙️",
            "japanese_open_for_business_button" => "🈺", "japanese_no_vacancy_button" => "🈵", "red_circle" => "🔴", "orange_circle" => "🟠",
            "yellow_circle" => "🟡", "green_circle" => "🟢", "blue_circle" => "🔵", "purple_circle" => "🟣",
            "brown_circle" => "🟤", "black_circle" => "⚫", "white_circle" => "⚪", "red_square" => "🟥",
            "orange_square" => "🟧", "yellow_square" => "🟨", "green_square" => "🟩", "blue_square" => "🟦",
            "purple_square" => "🟪", "brown_square" => "🟫", "black_large_square" => "⬛", "white_large_square" => "⬜",
            "black_medium_square" => "◼️", "white_medium_square" => "◻️", "black_medium_small_square" => "◾", "white_medium_small_square" => "◽",
            "black_small_square" => "▪️", "white_small_square" => "▫️", "large_orange_diamond" => "🔶", "large_blue_diamond" => "🔷",
            "small_orange_diamond" => "🔸", "small_blue_diamond" => "🔹", "red_triangle_pointed_up" => "🔺", "red_triangle_pointed_down" => "🔻",
            "diamond_with_a_dot" => "💠", "radio_button" => "🔘", "white_square_button" => "🔳", "black_square_button" => "🔲",
            "chequered_flag" => "🏁", "triangular_flag" => "🚩", "crossed_flags" => "🎌", "black_flag" => "🏴",
            "white_flag" => "🏳️", "rainbow_flag" => "🏳️‍🌈", "transgender_flag" => "🏳️‍⚧️", "pirate_flag" => "🏴‍☠️",
            "flag_ascension_island" => "🇦🇨", "flag_andorra" => "🇦🇩", "flag_united_arab_emirates" => "🇦🇪", "flag_afghanistan" => "🇦🇫",
            "flag_antigua_barbuda" => "🇦🇬", "flag_anguilla" => "🇦🇮", "flag_albania" => "🇦🇱", "flag_armenia" => "🇦🇲",
            "flag_angola" => "🇦🇴", "flag_antarctica" => "🇦🇶", "flag_argentina" => "🇦🇷", "flag_american_samoa" => "🇦🇸",
            "flag_austria" => "🇦🇹", "flag_australia" => "🇦🇺", "flag_aruba" => "🇦🇼", "flag_aland_islands" => "🇦🇽",
            "flag_azerbaijan" => "🇦🇿", "flag_bosnia_herzegovina" => "🇧🇦", "flag_barbados" => "🇧🇧", "flag_bangladesh" => "🇧🇩",
            "flag_belgium" => "🇧🇪", "flag_burkina_faso" => "🇧🇫", "flag_bulgaria" => "🇧🇬", "flag_bahrain" => "🇧🇭",
            "flag_burundi" => "🇧🇮", "flag_benin" => "🇧🇯", "flag_st_barthelemy" => "🇧🇱", "flag_bermuda" => "🇧🇲",
            "flag_brunei" => "🇧🇳", "flag_bolivia" => "🇧🇴", "flag_caribbean_netherlands" => "🇧🇶", "flag_brazil" => "🇧🇷",
            "flag_bahamas" => "🇧🇸", "flag_bhutan" => "🇧🇹", "flag_bouvet_island" => "🇧🇻", "flag_botswana" => "🇧🇼",
            "flag_belarus" => "🇧🇾", "flag_belize" => "🇧🇿", "flag_canada" => "🇨🇦", "flag_cocos_islands" => "🇨🇨",
            "flag_congo_kinshasa" => "🇨🇩", "flag_central_african_republic" => "🇨🇫", "flag_congo_brazzaville" => "🇨🇬", "flag_switzerland" => "🇨🇭",
            "flag_cote_d_ivoire" => "🇨🇮", "flag_cook_islands" => "🇨🇰", "flag_chile" => "🇨🇱", "flag_cameroon" => "🇨🇲",
            "flag_china" => "🇨🇳", "flag_colombia" => "🇨🇴", "flag_clipperton_island" => "🇨🇵", "flag_sark" => "🇨🇶",
            "flag_costa_rica" => "🇨🇷", "flag_cuba" => "🇨🇺", "flag_cape_verde" => "🇨🇻", "flag_curacao" => "🇨🇼",
            "flag_christmas_island" => "🇨🇽", "flag_cyprus" => "🇨🇾", "flag_czechia" => "🇨🇿", "flag_germany" => "🇩🇪",
            "flag_diego_garcia" => "🇩🇬", "flag_djibouti" => "🇩🇯", "flag_denmark" => "🇩🇰", "flag_dominica" => "🇩🇲",
            "flag_dominican_republic" => "🇩🇴", "flag_algeria" => "🇩🇿", "flag_ceuta_melilla" => "🇪🇦", "flag_ecuador" => "🇪🇨",
            "flag_estonia" => "🇪🇪", "flag_egypt" => "🇪🇬", "flag_western_sahara" => "🇪🇭", "flag_eritrea" => "🇪🇷",
            "flag_spain" => "🇪🇸", "flag_ethiopia" => "🇪🇹", "flag_european_union" => "🇪🇺", "flag_finland" => "🇫🇮",
            "flag_fiji" => "🇫🇯", "flag_falkland_islands" => "🇫🇰", "flag_micronesia" => "🇫🇲", "flag_faroe_islands" => "🇫🇴",
            "flag_france" => "🇫🇷", "flag_gabon" => "🇬🇦", "flag_united_kingdom" => "🇬🇧", "flag_grenada" => "🇬🇩",
            "flag_georgia" => "🇬🇪", "flag_french_guiana" => "🇬🇫", "flag_guernsey" => "🇬🇬", "flag_ghana" => "🇬🇭",
            "flag_gibraltar" => "🇬🇮", "flag_greenland" => "🇬🇱", "flag_gambia" => "🇬🇲", "flag_guinea" => "🇬🇳",
            "flag_guadeloupe" => "🇬🇵", "flag_equatorial_guinea" => "🇬🇶", "flag_greece" => "🇬🇷", "flag_south_georgia_south_sandwich_islands" => "🇬🇸",
            "flag_guatemala" => "🇬🇹", "flag_guam" => "🇬🇺", "flag_guinea_bissau" => "🇬🇼", "flag_guyana" => "🇬🇾",
            "flag_hong_kong_sar_china" => "🇭🇰", "flag_heard_mcdonald_islands" => "🇭🇲", "flag_honduras" => "🇭🇳", "flag_croatia" => "🇭🇷",
            "flag_haiti" => "🇭🇹", "flag_hungary" => "🇭🇺", "flag_canary_islands" => "🇮🇨", "flag_indonesia" => "🇮🇩",
            "flag_ireland" => "🇮🇪", "flag_israel" => "🇮🇱", "flag_isle_of_man" => "🇮🇲", "flag_india" => "🇮🇳",
            "flag_british_indian_ocean_territory" => "🇮🇴", "flag_iraq" => "🇮🇶", "flag_iran" => "🇮🇷", "flag_iceland" => "🇮🇸",
            "flag_italy" => "🇮🇹", "flag_jersey" => "🇯🇪", "flag_jamaica" => "🇯🇲", "flag_jordan" => "🇯🇴",
            "flag_japan" => "🇯🇵", "flag_kenya" => "🇰🇪", "flag_kyrgyzstan" => "🇰🇬", "flag_cambodia" => "🇰🇭",
            "flag_kiribati" => "🇰🇮", "flag_comoros" => "🇰🇲", "flag_st_kitts_nevis" => "🇰🇳", "flag_north_korea" => "🇰🇵",
            "flag_south_korea" => "🇰🇷", "flag_kuwait" => "🇰🇼", "flag_cayman_islands" => "🇰🇾", "flag_kazakhstan" => "🇰🇿",
            "flag_laos" => "🇱🇦", "flag_lebanon" => "🇱🇧", "flag_st_lucia" => "🇱🇨", "flag_liechtenstein" => "🇱🇮",
            "flag_sri_lanka" => "🇱🇰", "flag_liberia" => "🇱🇷", "flag_lesotho" => "🇱🇸", "flag_lithuania" => "🇱🇹",
            "flag_luxembourg" => "🇱🇺", "flag_latvia" => "🇱🇻", "flag_libya" => "🇱🇾", "flag_morocco" => "🇲🇦",
            "flag_monaco" => "🇲🇨", "flag_moldova" => "🇲🇩", "flag_montenegro" => "🇲🇪", "flag_st_martin" => "🇲🇫",
            "flag_madagascar" => "🇲🇬", "flag_marshall_islands" => "🇲🇭", "flag_north_macedonia" => "🇲🇰", "flag_mali" => "🇲🇱",
            "flag_myanmar" => "🇲🇲", "flag_mongolia" => "🇲🇳", "flag_macao_sar_china" => "🇲🇴", "flag_northern_mariana_islands" => "🇲🇵",
            "flag_martinique" => "🇲🇶", "flag_mauritania" => "🇲🇷", "flag_montserrat" => "🇲🇸", "flag_malta" => "🇲🇹",
            "flag_mauritius" => "🇲🇺", "flag_maldives" => "🇲🇻", "flag_malawi" => "🇲🇼", "flag_mexico" => "🇲🇽",
            "flag_malaysia" => "🇲🇾", "flag_mozambique" => "🇲🇿", "flag_namibia" => "🇳🇦", "flag_new_caledonia" => "🇳🇨",
            "flag_niger" => "🇳🇪", "flag_norfolk_island" => "🇳🇫", "flag_nigeria" => "🇳🇬", "flag_nicaragua" => "🇳🇮",
            "flag_netherlands" => "🇳🇱", "flag_norway" => "🇳🇴", "flag_nepal" => "🇳🇵", "flag_nauru" => "🇳🇷",
            "flag_niue" => "🇳🇺", "flag_new_zealand" => "🇳🇿", "flag_oman" => "🇴🇲", "flag_panama" => "🇵🇦",
            "flag_peru" => "🇵🇪", "flag_french_polynesia" => "🇵🇫", "flag_papua_new_guinea" => "🇵🇬", "flag_philippines" => "🇵🇭",
            "flag_pakistan" => "🇵🇰", "flag_poland" => "🇵🇱", "flag_st_pierre_miquelon" => "🇵🇲", "flag_pitcairn_islands" => "🇵🇳",
            "flag_puerto_rico" => "🇵🇷", "flag_palestinian_territories" => "🇵🇸", "flag_portugal" => "🇵🇹", "flag_palau" => "🇵🇼",
            "flag_paraguay" => "🇵🇾", "flag_qatar" => "🇶🇦", "flag_reunion" => "🇷🇪", "flag_romania" => "🇷🇴",
            "flag_serbia" => "🇷🇸", "flag_russia" => "🇷🇺", "flag_rwanda" => "🇷🇼", "flag_saudi_arabia" => "🇸🇦",
            "flag_solomon_islands" => "🇸🇧", "flag_seychelles" => "🇸🇨", "flag_sudan" => "🇸🇩", "flag_sweden" => "🇸🇪",
            "flag_singapore" => "🇸🇬", "flag_st_helena" => "🇸🇭", "flag_slovenia" => "🇸🇮", "flag_svalbard_jan_mayen" => "🇸🇯",
            "flag_slovakia" => "🇸🇰", "flag_sierra_leone" => "🇸🇱", "flag_san_marino" => "🇸🇲", "flag_senegal" => "🇸🇳",
            "flag_somalia" => "🇸🇴", "flag_suriname" => "🇸🇷", "flag_south_sudan" => "🇸🇸", "flag_sao_tome_principe" => "🇸🇹",
            "flag_el_salvador" => "🇸🇻", "flag_sint_maarten" => "🇸🇽", "flag_syria" => "🇸🇾", "flag_eswatini" => "🇸🇿",
            "flag_tristan_da_cunha" => "🇹🇦", "flag_turks_caicos_islands" => "🇹🇨", "flag_chad" => "🇹🇩", "flag_french_southern_territories" => "🇹🇫",
            "flag_togo" => "🇹🇬", "flag_thailand" => "🇹🇭", "flag_tajikistan" => "🇹🇯", "flag_tokelau" => "🇹🇰",
            "flag_timor_leste" => "🇹🇱", "flag_turkmenistan" => "🇹🇲", "flag_tunisia" => "🇹🇳", "flag_tonga" => "🇹🇴",
            "flag_turkiye" => "🇹🇷", "flag_trinidad_tobago" => "🇹🇹", "flag_tuvalu" => "🇹🇻", "flag_taiwan" => "🇹🇼",
            "flag_tanzania" => "🇹🇿", "flag_ukraine" => "🇺🇦", "flag_uganda" => "🇺🇬", "flag_u_s_outlying_islands" => "🇺🇲",
            "flag_united_nations" => "🇺🇳", "flag_united_states" => "🇺🇸", "flag_uruguay" => "🇺🇾", "flag_uzbekistan" => "🇺🇿",
            "flag_vatican_city" => "🇻🇦", "flag_st_vincent_grenadines" => "🇻🇨", "flag_venezuela" => "🇻🇪", "flag_british_virgin_islands" => "🇻🇬",
            "flag_u_s_virgin_islands" => "🇻🇮", "flag_vietnam" => "🇻🇳", "flag_vanuatu" => "🇻🇺", "flag_wallis_futuna" => "🇼🇫",
            "flag_samoa" => "🇼🇸", "flag_kosovo" => "🇽🇰", "flag_yemen" => "🇾🇪", "flag_mayotte" => "🇾🇹",
            "flag_south_africa" => "🇿🇦", "flag_zambia" => "🇿🇲", "flag_zimbabwe" => "🇿🇼", "flag_england" => "🏴󠁧󠁢󠁥󠁮󠁧󠁿",
            "flag_scotland" => "🏴󠁧󠁢󠁳󠁣󠁴󠁿", "flag_wales" => "🏴󠁧󠁢󠁷󠁬󠁳󠁿",
        ];


        // Match the emoji code pattern (e.g., `:smile:`) only if it's standalone and not embedded in a word
        if (preg_match('/(?<=\s|^):([a-zA-Z0-9_]+):(?=\s|$)/', $Excerpt['text'], $matches) && preg_match('/^(\s|)$/', $Excerpt['before'])) {
            $emojiCode = $matches[1]; // Extract the emoji code without colons

            // Check if the emoji code exists in the map
            if (isset($emojiMap[$emojiCode])) {
                return [
                    'extent' => strlen($matches[0]), // Length of the matched emoji code including colons
                    'element' => [
                        'text' => $emojiMap[$emojiCode], // Replace emoji code with corresponding emoji
                    ],
                ];
            }
        }

        // If no emoji code matches, return null
        return null;
    }


    // Block types
    // -------------------------------------------------------------------------

    /**
     * Parses attribute data for headings.
     *
     * Handles parsing of attribute data for headings if the feature is enabled.
     *
     * @since 0.1.0
     *
     * @param string $attributeString The attribute string to be parsed.
     * @return array The parsed attributes or an empty array if not applicable.
     */
    protected function parseAttributeData($attributeString)
    {
        // Check if special attributes for headings are enabled
        if ($this->config()->get('headings.special_attributes')) {
            return parent::parseAttributeData($attributeString); // Delegate to parent class
        }

        return []; // Return an empty array if the feature is disabled
    }

    /**
     * Handles the parsing of footnote blocks.
     *
     * @since 0.1.0
     *
     * @param array $Line The line to be processed as a footnote.
     * @return mixed The parsed footnote block if enabled, otherwise nothing.
     */
    protected function blockFootnote($Line)
    {
        // Check if footnotes are enabled
        if ($this->config()->get('footnotes')) {
            return parent::blockFootnote($Line); // Delegate to parent class
        }

        return null;
    }

    /**
     * Handles the parsing of definition list blocks.
     *
     * @since 0.1.0
     *
     * @param array $Line The current line to be processed.
     * @param array $Block The current block context.
     * @return mixed The parsed definition list block if enabled, otherwise nothing.
     */
    protected function blockDefinitionList($Line, $Block)
    {
        // Check if definition lists are enabled
        if ($this->config()->get('definition_lists')) {
            return parent::blockDefinitionList($Line, $Block); // Delegate to parent class
        }

        return null;
    }

    /**
     * Handles the parsing of code blocks.
     *
     * @since 0.1.0
     *
     * @param array $Line The current line to be processed.
     * @param array|null $Block The current block context.
     * @return mixed The parsed code block if enabled, otherwise nothing.
     */
    protected function blockCode($Line, $Block = null)
    {
        // Check if code blocks are enabled
        if ($this->config()->get('code') && $this->config()->get('code.blocks')) {
            return parent::blockCode($Line, $Block); // Delegate to parent class
        }

        return null;
    }

    /**
     * Handles the parsing of HTML comment blocks.
     *
     * @since 0.1.0
     *
     * @param array $Line The current line to be processed as a comment.
     * @return mixed The parsed comment block if enabled, otherwise nothing.
     */
    protected function blockComment($Line)
    {
        // Check if HTML comments are enabled
        if ($this->config()->get('comments')) {
            return parent::blockComment($Line); // Delegate to parent class
        }

        return null;
    }

    /**
     * Handles the parsing of list blocks.
     *
     * @since 0.1.0
     *
     * @param array $Line The current line to be processed.
     * @param array|null $CurrentBlock The current block context.
     * @return mixed The parsed list block if enabled, otherwise nothing.
     */
    protected function blockList($Line, ?array $CurrentBlock = null)
    {
        // Check if lists are enabled
        if ($this->config()->get('lists')) {
            return parent::blockList($Line, $CurrentBlock); // Delegate to parent class
        }

        return null;
    }

    /**
     * Handles the parsing of block quote elements.
     *
     * @since 0.1.0
     *
     * @param array $Line The current line to be processed as a block quote.
     * @return mixed The parsed block quote if enabled, otherwise nothing.
     */
    protected function blockQuote($Line)
    {
        // Check if block quotes are enabled
        if ($this->config()->get('quotes')) {
            return parent::blockQuote($Line); // Delegate to parent class
        }

        return null;
    }

    /**
     * Handles the parsing of horizontal rule blocks.
     *
     * @since 0.1.0
     *
     * @param array $Line The current line to be processed.
     * @return mixed The parsed horizontal rule if enabled, otherwise nothing.
     */
    protected function blockRule($Line)
    {
        // Check if thematic breaks (horizontal rules) are enabled
        if ($this->config()->get('thematic_breaks')) {
            return parent::blockRule($Line); // Delegate to parent class
        }

        return null;
    }

    /**
     * Handles the parsing of raw HTML markup blocks.
     *
     * @since 0.1.0
     *
     * @param array $Line The current line to be processed as raw HTML.
     * @return mixed The parsed HTML block if allowed, otherwise nothing.
     */
    protected function blockMarkup($Line)
    {
        // Check if raw HTML is allowed
        if ($this->config()->get('allow_raw_html')) {
            return parent::blockMarkup($Line); // Delegate to parent class
        }

        return null;
    }

    /**
     * Handles the parsing of reference blocks.
     *
     * @since 0.1.0
     *
     * @param array $Line The current line to be processed as a reference.
     * @return mixed The parsed reference block if enabled, otherwise nothing.
     */
    protected function blockReference($Line)
    {
        // Check if references are enabled
        if ($this->config()->get('references')) {
            return parent::blockReference($Line); // Delegate to parent class
        }

        return null;
    }

    /**
     * Handles the parsing of table blocks.
     *
     * @since 0.1.0
     *
     * @param array $Line The current line to be processed.
     * @param array|null $Block The current block context.
     * @return mixed The parsed table block if enabled, otherwise nothing.
     */
    protected function blockTable($Line, $Block = null)
    {
        // Check if tables are enabled
        if ($this->config()->get('tables')) {
            return parent::blockTable($Line, $Block); // Delegate to parent class
        }

        return null;
    }


    /**
     * Processes alert blocks within the parsed Markdown text.
     *
     * This function identifies and processes blocks starting with a specific alert syntax, such as `> [!NOTE]`.
     * Alerts are styled based on their type (e.g., Note, Warning, etc.) and formatted as HTML div elements with appropriate classes.
     *
     * @since 1.3.0
     *
     * @param array $Line The line being processed for an alert block.
     * @return array|null The parsed alert block if matched, otherwise null.
     */
    protected function blockAlert($Line): ?array
    {
        // Check if alerts are enabled in the configuration settings
        if (!$this->config()->get('alerts.enabled')) {
            return null; // Return null if alert blocks are disabled
        }

        // Retrieve the alert types from the config (e.g., 'NOTE', 'WARNING')
        $alertTypes = $this->config()->get('alerts.types');

        // Build the regex pattern dynamically based on the alert types
        $alertTypesPattern = implode('|', array_map('strtoupper', $alertTypes));

        // Create the full regex pattern for matching alert block syntax
        $pattern = '/^> \[!(' . $alertTypesPattern . ')\]/';

        // Check if the line matches the alert pattern
        if (preg_match($pattern, $Line['text'], $matches)) {
            $type = strtolower($matches[1]); // Extract the alert type and convert to lowercase
            $title = ucfirst($type); // Capitalize the first letter for the alert title

            // Get class name for alerts from the configuration
            $class = $this->config()->get('alerts.class');

            // Build the alert block with appropriate HTML attributes and content
            return [
                'element' => [
                    'name' => 'div',
                    'attributes' => [
                        'class' => "{$class} {$class}-{$type}", // Add alert type as a class (e.g., 'alert alert-note')
                    ],
                    'handler' => 'elements', // Use 'elements' because we'll be adding more content elements later
                    'text' => [
                        [
                            'name' => 'p',
                            'attributes' => [
                                'class' => "{$class}-title", // Assign title-specific class for the alert
                            ],
                            'text' => $title, // Set the alert title (e.g., "Note")
                        ],
                    ],
                ],
            ]; // Return the parsed alert block
        }

        return null; // Return null if the line does not match the alert pattern
    }

    /**
     * Continues processing alert blocks by adding subsequent lines to the current alert block.
     *
     * @since 1.3.0
     *
     * @param array $Line The current line being processed.
     * @param array $Block The current block being extended.
     * @return array|null The updated alert block or null if the continuation is not applicable.
     */
    protected function blockAlertContinue($Line, array $Block)
    {
        // Retrieve the alert types from the config (e.g., 'NOTE', 'WARNING')
        $alertTypes = $this->config()->get('alerts.types');

        // Build the regex pattern dynamically based on the alert types
        $alertTypesPattern = implode('|', array_map('strtoupper', $alertTypes));

        // Create the full regex pattern for identifying new alert blocks
        $pattern = '/^> \[!(' . $alertTypesPattern . ')\]/';

        // If the line matches a new alert block, terminate the current one
        if (preg_match($pattern, $Line['text'])) {
            return null; // Return null to terminate the current alert block
        }

        // Check if the line continues the current alert block with '>' followed by content
        if ($Line['text'][0] === '>' && preg_match('/^> ?(.*)/', $Line['text'], $matches)) {
            // If the block was interrupted, add an empty paragraph for spacing
            if (isset($Block['interrupted'])) {
                $Block['element']['text'][] = ['text' => ''];
                unset($Block['interrupted']); // Reset the interrupted status
            }

            // Append the new line content to the current block
            $Block['element']['text'][] = [
                'name' => 'p',
                'text' => $matches[1], // Add the text following the '>'
            ];

            return $Block; // Return the updated block
        }

        // If the line does not start with '>' and the block is not interrupted, append it
        if (!isset($Block['interrupted'])) {
            $Block['element']['text'][] = [
                'name' => 'p',
                'text' => $Line['text'], // Add the text directly to the alert block
            ];

            return $Block; // Return the updated block
        }

        return null; // Return null if the continuation conditions are not met
    }

    /**
     * Completes the alert block.
     *
     * @since 1.3.0
     *
     * @param array $Block The current block being finalized.
     * @return array The completed alert block.
     */
    protected function blockAlertComplete($Block)
    {
        return $Block; // Finalize and return the alert block
    }


    /**
     * Processes block-level math notation.
     *
     * This function identifies and processes blocks of text surrounded by specific math delimiters (e.g., `$$` or `\\[ ... \\]`)
     * to be formatted as math elements.
     *
     * @since 1.1.2
     *
     * @param array $Line The line being processed for a math block.
     * @return array|null The parsed math block if matched, otherwise null.
     */
    protected function blockMathNotation($Line)
    {
        // Check if math notation block-level parsing is enabled in the configuration settings
        if (!$this->config()->get('math') || !$this->config()->get('math.block')) {
            return null; // Return null if math block parsing is disabled
        }

        // Iterate over each configured math block delimiter (e.g., `$$`, `\\[`)
        foreach ($this->config()->get('math.block.delimiters') as $config) {

            // Escape the math delimiters for regex usage
            $leftMarker = preg_quote($config['left'], '/');
            $rightMarker = preg_quote($config['right'], '/');

            // Build the regex pattern to match the opening delimiter, content, and optional closing delimiter
            $regex = '/^(?<!\\\\)('. $leftMarker . ')(.*?)(?:(' . $rightMarker . ')|$)/';

            // Check if the line matches the math block pattern
            if (preg_match($regex, $Line['text'], $matches)) {
                return [
                    'element' => [
                        'text' => $matches[2], // Extract and store the math content between the delimiters
                    ],
                    'start' => $config['left'], // Store the start marker (e.g., `$$`)
                    'end' => $config['right'], // Store the end marker (e.g., `$$`)
                ];
            }
        }

        return null; // Return null if the line does not match any configured math block pattern
    }

    /**
     * Continues processing block-level math notation by adding subsequent lines.
     *
     * This function handles the continuation of a math block until the closing delimiter is found.
     *
     * @since 1.1.2
     *
     * @param array $Line The current line being processed.
     * @param array $Block The current math block being extended.
     * @return array|null The updated math block or null if the continuation is not applicable.
     */
    protected function blockMathNotationContinue($Line, $Block)
    {
        // If the math block is already complete, return null
        if (isset($Block['complete'])) {
            return null;
        }

        // Handle interrupted lines in the math block by adding newlines
        if (isset($Block['interrupted'])) {
            // Convert the 'interrupted' flag to an integer to determine the number of newlines
            $Block['interrupted'] = (int) $Block['interrupted'];

            // Append the appropriate number of newlines to maintain line breaks
            $Block['element']['text'] .= str_repeat("\n", $Block['interrupted']);
            unset($Block['interrupted']); // Reset the interrupted flag
        }

        // Double escape the right marker to properly build the regex pattern for closing delimiter
        $rightMarker = preg_quote($Block['end'], '/');
        $regex = '/^(?<!\\\\)(' . $rightMarker . ')(.*)/';

        // Check if the current line contains the closing delimiter
        if (preg_match($regex, $Line['text'], $matches)) {
            $Block['complete'] = true; // Mark the block as complete
            $Block['math'] = true; // Indicate this is a math block
            $Block['element']['text'] = $Block['start'] . $Block['element']['text'] . $Block['end'] . $matches[2];

            return $Block; // Return the completed block
        }

        // Append the current line's text to the math block
        $Block['element']['text'] .= "\n" . $Line['body'];

        return $Block; // Return the updated block
    }

    /**
     * Completes the block-level math notation.
     *
     * This function is called when a math block is finalized.
     *
     * @since 1.1.2
     *
     * @param array $Block The current block being finalized.
     * @return array The completed math block.
     */
    protected function blockMathNotationComplete($Block)
    {
        return $Block; // Finalize and return the completed math block
    }


    /**
     * Processes fenced code blocks with special handling for extensions like Mermaid and Chart.js.
     *
     * This function extends the standard fenced code block parsing to handle additional languages that may
     * require specific rendering, such as diagrams (e.g., Mermaid, Chart.js). The type of element rendered depends
     * on the specified language, and different HTML elements may be used based on the context.
     *
     * @since 0.1.0
     *
     * @param array $Line The line being processed for a fenced code block.
     * @return array|null The parsed code block or diagram block if applicable, otherwise null.
     */
    protected function blockFencedCode($Line)
    {
        // Check if code block parsing is enabled in the configuration settings
        if (!$this->config()->get('code') || !$this->config()->get('code.blocks')) {
            return null; // Return null if code block parsing is disabled
        }

        // Use the parent class to parse the fenced code block
        $Block = parent::blockFencedCode($Line);
        $marker = $Line['text'][0]; // Identify the marker character (e.g., backticks)
        $openerLength = strspn($Line['text'], $marker); // Determine the length of the opening markers

        // Extract the language identifier from the fenced code line
        $parts = explode(' ', trim(substr($Line['text'], $openerLength)), 2);
        $language = strtolower($parts[0]); // Convert the language identifier to lowercase

        // Check if diagram support is enabled in the configuration
        if (!$this->config()->get('diagrams')) {
            return $Block; // Return the standard code block if diagrams are disabled
        }

        // Define custom handlers for specific code block extensions like Mermaid and Chart.js
        $extensions = [
            'mermaid' => ['div', 'mermaid'], // Mermaid diagrams rendered inside a <div> with class "mermaid"
            'chart' => ['canvas', 'chartjs'], // Chart.js diagrams rendered inside a <canvas> with class "chartjs"
            // Additional languages can be added here as needed
        ];

        // If the specified language matches one of the configured extensions, customize the element
        if (isset($extensions[$language])) {
            [$elementName, $class] = $extensions[$language]; // Extract the element name and class for the language

            // Return different structures depending on the legacy mode setting
            if (!$this->legacyMode) {
                // Structure for version 1.8 or newer
                return [
                    'char' => $marker, // Store the marker character
                    'openerLength' => $openerLength, // Store the length of the opener
                    'element' => [
                        'name' => $elementName, // Set the element name (e.g., 'div', 'canvas')
                        'element' => [
                            'text' => '', // Placeholder for content
                        ],
                        'attributes' => [
                            'class' => $class, // Add the class for styling (e.g., 'mermaid', 'chartjs')
                        ],
                    ],
                ];
            } else {
                // Structure for version 1.7 or older
                return [
                    'char' => $marker, // Store the marker character
                    'openerLength' => $openerLength, // Store the length of the opener
                    'element' => [
                        'name' => $elementName, // Set the element name (e.g., 'div', 'canvas')
                        'handler' => 'element', // Handler type for processing elements
                        'text' => [
                            'text' => '', // Placeholder for content
                        ],
                        'attributes' => [
                            'class' => $class, // Add the class for styling (e.g., 'mermaid', 'chartjs')
                        ],
                    ],
                ];
            }
        }

        // Return the standard code block if no special handling is needed
        return $Block;
    }


    /**
     * Processes list items, including handling task list syntax for checkboxes.
     *
     * This function processes list items in Markdown and handles special task list syntax (e.g., `- [x]` or `- [ ]`).
     * It converts list items into appropriate HTML markup, rendering checkboxes when task lists are enabled.
     * The function also maintains compatibility with older parsing modes.
     *
     * @since 0.1.0
     *
     * @param array $lines The lines that make up the list item being processed.
     * @return mixed The parsed list item markup, either as a string for legacy mode or as an array of elements.
     */
    protected function li($lines)
    {
        // Check if task lists are enabled in the configuration settings
        if (!$this->config()->get('lists.tasks')) {
            return parent::li($lines); // Return the default list item if task lists are not enabled
        }

        $Elements = $this->linesElements($lines);

        // Extract the text of the first element to check for a task list checkbox
        $text = $Elements[0]['handler']['argument'];
        $firstFourChars = substr($text, 0, 4);

        // Check if the list item starts with a checkbox (e.g., `[x]` or `[ ]`)
        if (preg_match('/^\[[x ]\]/i', $firstFourChars, $matches)) {
            // Remove the checkbox marker from the beginning of the text
            $Elements[0]['handler']['argument'] = substr_replace($text, '', 0, 4);

            // Set the appropriate attributes based on whether the checkbox is checked or unchecked
            if (strtolower($matches[0]) === '[x]') {
                $Elements[0]['attributes'] = [
                    'class' => 'task-list-item-complete'
                ];
            } else {
                $Elements[0]['attributes'] = [
                    'class' => 'task-list-item'
                ];
            }

            // Set the element type to 'input' for the checkbox
            $Elements[0]['name'] = 'span';
        }

        // Remove unnecessary paragraph tags for the list item if not interrupted
        if (isset($Elements[0]['name']) && !in_array('', $lines) && $Elements[0]['name'] === 'p') {
            unset($Elements[0]['name']); // Remove paragraph wrapper
        }

        return $Elements; // Return the final array of elements for the list item
    }


    /**
     * Processes ATX-style headers (e.g., `# Header Text`).
     *
     * This function processes ATX-style headers, checks if the heading levels are allowed, generates an anchor ID for the
     * header, and adds it to the Table of Contents (TOC) if applicable.
     *
     * @since 0.1.0
     *
     * @param array $Line The line being processed to determine if it is a header.
     * @return array|null The parsed header block with added attributes or null if the header is not allowed.
     */
    protected function blockHeader($Line)
    {
        // Check if headings are enabled in the configuration settings
        if (!$this->config()->get('headings')) {
            return null; // Return null if headings are disabled
        }

        // Use the parent class to parse the header block
        $Block = parent::blockHeader($Line);

        if (!empty($Block)) {
            // Extract the text and level of the header
            $text = $Block['element']['text'] ?? $Block['element']['handler']['argument'] ?? '';
            $level = $Block['element']['name'];

            // Check if the header level is allowed (e.g., h1, h2, etc.)
            if (!in_array($level, $this->config()->get('headings.allowed_levels'))) {
                return null; // Return null if the heading level is not allowed
            }

            // Generate an anchor ID for the header element
            // If an ID attribute is not set, use the text to create the ID
            $id = $Block['element']['attributes']['id'] ?? $text;
            $id = $this->createAnchorID($id);

            // Set the 'id' attribute for the header element
            $Block['element']['attributes'] = ['id' => $id];

            // Check if the heading level should be included in the Table of Contents (TOC)
            if (!in_array($level, $this->config()->get('toc.levels'))) {
                return $Block; // Return the block if it should not be part of the TOC
            }

            // Add the heading to the Table of Contents
            $this->setContentsList(['text' => $text, 'id' => $id, 'level' => $level]);

            return $Block; // Return the modified header block
        }

        return null; // Return null if the header block is empty
    }

    /**
     * Processes Setext-style headers (e.g., `Header Text` followed by `===` or `---`).
     *
     * This function processes Setext-style headers, checks if the heading levels are allowed, generates an anchor ID for the
     * header, and adds it to the Table of Contents (TOC) if applicable.
     *
     * @since 0.1.0
     *
     * @param array $Line The line being processed for a Setext header.
     * @param array|null $Block The existing block context (if any).
     * @return array|null The parsed Setext header block with added attributes or null if the header is not allowed.
     */
    protected function blockSetextHeader($Line, $Block = null)
    {
        // Check if headings are enabled in the configuration settings
        if (!$this->config()->get('headings')) {
            return null; // Return null if headings are disabled
        }

        // Use the parent class to parse the Setext header block
        $Block = parent::blockSetextHeader($Line, $Block);

        if (!empty($Block)) {
            // Extract the text and level of the header
            $text = $Block['element']['text'] ?? $Block['element']['handler']['argument'] ?? '';
            $level = $Block['element']['name'];

            // Check if the header level is allowed (e.g., h1, h2, etc.)
            if (!in_array($level, $this->config()->get('headings.allowed_levels'))) {
                return null; // Return null if the heading level is not allowed
            }

            // Generate an anchor ID for the header element
            // If an ID attribute is not set, use the text to create the ID
            $id = $Block['element']['attributes']['id'] ?? $text;
            $id = $this->createAnchorID($id);

            // Set the 'id' attribute for the header element
            $Block['element']['attributes'] = ['id' => $id];

            // Check if the heading level should be included in the Table of Contents (TOC)
            if (!in_array($level, $this->config()->get('toc.levels'))) {
                return $Block; // Return the block if it should not be part of the TOC
            }

            // Add the heading to the Table of Contents
            $this->setContentsList(['text' => $text, 'id' => $id, 'level' => $level]);

            return $Block; // Return the modified Setext header block
        }

        return null; // Return null if the Setext header block is empty
    }


    /**
     * Processes abbreviation blocks.
     *
     * This function handles the parsing of abbreviation definitions. It checks if abbreviations are enabled
     * in the configuration and whether custom abbreviations are allowed. If custom abbreviations are allowed,
     * it delegates the parsing to the parent class method.
     *
     * @since 0.1.0
     *
     * @param array $Line The line being processed to determine if it defines an abbreviation.
     * @return array|null The parsed abbreviation block or null if abbreviations are disabled or custom abbreviations are not allowed.
     */
    protected function blockAbbreviation($Line)
    {
        // Check if abbreviation support is enabled in the configuration settings
        if ($this->config()->get('abbreviations')) {

            // If custom abbreviations are allowed, delegate to the parent class to handle parsing
            if ($this->config()->get('abbreviations.allow_custom')) {
                return parent::blockAbbreviation($Line); // Parse custom abbreviation using parent method
            }

            // If custom abbreviations are not allowed, return null to prevent processing
            return null;
        }

        // Return null if abbreviations are completely disabled in the configuration
        return null;
    }


    /**
     * Completes the processing of table blocks.
     *
     * This function processes table blocks after the initial parsing to handle special features such as column spans
     * and row spans. It processes each cell in the table, merging cells where indicated by specific characters
     * (e.g., '>' for colspan and '^' for rowspan). The implementation handles both legacy and modern parsing modes.
     *
     * @since 1.0.1
     *
     * @param array $block The parsed table block to be processed further.
     * @return array The completed and modified table block.
     */
    protected function blockTableComplete(array $block): array
    {
        // Check if table spanning (colspan and rowspan) is enabled
        if (!$this->config()->get('tables.tablespan')) {
            return $block; // Return the original block if spanning is not enabled
        }

        // Reference to header elements depending on legacy mode or newer version
        if ($this->legacyMode === true) {
            // Version 1.7
            $headerElements = &$block['element']['text'][0]['text'][0]['text'];
        } else {
            // Version 1.8
            $headerElements = &$block['element']['elements'][0]['elements'][0]['elements'];
        }

        // Process colspan in header elements
        for ($index = count($headerElements) - 1; $index >= 0; --$index) {
            $colspan = 1;
            $headerElement = &$headerElements[$index];

            if ($this->legacyMode === true) {
                // Version 1.7
                while ($index && $headerElements[$index - 1]['text'] === '>') {
                    $colspan++;
                    $PreviousHeaderElement = &$headerElements[--$index];
                    $PreviousHeaderElement['merged'] = true;
                    if (isset($PreviousHeaderElement['attributes'])) {
                        $headerElement['attributes'] = $PreviousHeaderElement['attributes'];
                    }
                }
            } else {
                // Version 1.8
                while ($index && '>' === $headerElements[$index - 1]['handler']['argument']) {
                    $colspan++;
                    $PreviousHeaderElement = &$headerElements[--$index];
                    $PreviousHeaderElement['merged'] = true;
                    if (isset($PreviousHeaderElement['attributes'])) {
                        $headerElement['attributes'] = $PreviousHeaderElement['attributes'];
                    }
                }
            }

            // Assign colspan attribute if colspan is greater than 1
            if ($colspan > 1) {
                if (!isset($headerElement['attributes'])) {
                    $headerElement['attributes'] = [];
                }
                $headerElement['attributes']['colspan'] = $colspan;
            }
        }

        // Remove merged header elements
        for ($index = count($headerElements) - 1; $index >= 0; --$index) {
            if (isset($headerElements[$index]['merged'])) {
                array_splice($headerElements, $index, 1);
            }
        }

        // Reference to table rows based on legacy or modern mode
        if ($this->legacyMode === true) {
            // Version 1.7
            $rows = &$block['element']['text'][1]['text'];
        } else {
            // Version 1.8
            $rows = &$block['element']['elements'][1]['elements'];
        }

        // Process colspan for rows
        foreach ($rows as &$row) {
            if ($this->legacyMode === true) {
                // Version 1.7
                $elements = &$row['text'];
            } else {
                // Version 1.8
                $elements = &$row['elements'];
            }

            for ($index = count($elements) - 1; $index >= 0; --$index) {
                $colspan = 1;
                $element = &$elements[$index];

                if ($this->legacyMode === true) {
                    // Version 1.7
                    while ($index && $elements[$index - 1]['text'] === '>') {
                        $colspan++;
                        $PreviousElement = &$elements[--$index];
                        $PreviousElement['merged'] = true;
                        if (isset($PreviousElement['attributes'])) {
                            $element['attributes'] = $PreviousElement['attributes'];
                        }
                    }
                } else {
                    // Version 1.8
                    while ($index && '>' === $elements[$index - 1]['handler']['argument']) {
                        ++$colspan;
                        $PreviousElement = &$elements[--$index];
                        $PreviousElement['merged'] = true;
                        if (isset($PreviousElement['attributes'])) {
                            $element['attributes'] = $PreviousElement['attributes'];
                        }
                    }
                }

                // Assign colspan attribute if colspan is greater than 1
                if ($colspan > 1) {
                    if (!isset($element['attributes'])) {
                        $element['attributes'] = [];
                    }
                    $element['attributes']['colspan'] = $colspan;
                }
            }
        }

        // Process rowspan for rows
        foreach ($rows as $rowNo => &$row) {
            if ($this->legacyMode === true) {
                // Version 1.7
                $elements = &$row['text'];
            } else {
                // Version 1.8
                $elements = &$row['elements'];
            }

            foreach ($elements as $index => &$element) {
                $rowspan = 1;

                if (isset($element['merged'])) {
                    continue; // Skip merged elements
                }

                if ($this->legacyMode === true) {
                    // Version 1.7
                    while (
                        $rowNo + $rowspan < count($rows) &&
                        $index < count($rows[$rowNo + $rowspan]['text']) &&
                        $rows[$rowNo + $rowspan]['text'][$index]['text'] === '^' &&
                        (@$element['attributes']['colspan'] ?: null) === (@$rows[$rowNo + $rowspan]['text'][$index]['attributes']['colspan'] ?: null)
                    ) {
                        $rows[$rowNo + $rowspan]['text'][$index]['merged'] = true;
                        $rowspan++;
                    }
                } else {
                    // Version 1.8
                    while (
                        $rowNo + $rowspan < count($rows) &&
                        $index < count($rows[$rowNo + $rowspan]['elements']) &&
                        '^' === $rows[$rowNo + $rowspan]['elements'][$index]['handler']['argument'] &&
                        (@$element['attributes']['colspan'] ?: null) === (@$rows[$rowNo + $rowspan]['elements'][$index]['attributes']['colspan'] ?: null)
                    ) {
                        $rows[$rowNo + $rowspan]['elements'][$index]['merged'] = true;
                        $rowspan++;
                    }
                }

                // Assign rowspan attribute if rowspan is greater than 1
                if ($rowspan > 1) {
                    if (!isset($element['attributes'])) {
                        $element['attributes'] = [];
                    }
                    $element['attributes']['rowspan'] = $rowspan;
                }
            }
        }

        // Remove merged elements after processing row spans
        foreach ($rows as &$row) {
            if ($this->legacyMode === true) {
                // Version 1.7
                $elements = &$row['text'];
            } else {
                // Version 1.8
                $elements = &$row['elements'];
            }

            for ($index = count($elements) - 1; $index >= 0; --$index) {
                if (isset($elements[$index]['merged'])) {
                    array_splice($elements, $index, 1); // Remove merged element
                }
            }
        }

        return $block; // Return the completed and modified table block
    }


    // Functions related to Table of Contents
    // Modified version of ToC by @KEINOS
    // -------------------------------------------------------------------------

    /**
     * Parses the provided text and handles escaping/unescaping of ToC tags.
     *
     * This function processes the given text, escaping the ToC tags temporarily,
     * parsing the Markdown text into HTML, and then unescaping the ToC tags to
     * include them in the final output.
     *
     * @since 1.0.0
     *
     * @param string $text The input Markdown text to be parsed.
     * @return string The parsed HTML text with ToC tags properly handled.
     */
    public function body(string $text): string
    {
        $text = $this->encodeTag($text); // Escapes ToC tag temporarily
        $html = parent::text($text);     // Parses the markdown text
        return $this->decodeTag($html);  // Unescapes the ToC tag
    }

    /**
     * Retrieves the Table of Contents (ToC) in the specified format.
     *
     * This function returns the ToC either as a formatted string or as a JSON
     * string. If an unknown type is provided, an exception is thrown.
     *
     * @since 1.0.0
     *
     * @param string $type_return The desired return format: 'string' or 'json'.
     * @return string The Table of Contents in the specified format.
     * @throws \InvalidArgumentException If an unknown return type is provided.
     */
    public function contentsList(string $type_return = 'string'): string
    {
        switch (strtolower($type_return)) {
            case 'string':
                return $this->contentsListString ? $this->body($this->contentsListString) : '';
            case 'json':
                return json_encode($this->contentsListArray);
            default:
                $backtrace = debug_backtrace();
                $caller = $backtrace[1] ?? $backtrace[0];
                $errorMessage = "Unknown return type '{$type_return}' given while parsing ToC. Called in " . ($caller['file'] ?? 'unknown') . " on line " . ($caller['line'] ?? 'unknown');
                throw new \InvalidArgumentException($errorMessage);
        }
    }

    /**
     * Sets a callback function for creating anchor IDs for headers.
     *
     * This allows the user to provide custom logic for generating anchor IDs for
     * the headers found in the Markdown content.
     *
     * @since 1.2.0
     *
     * @param callable $callback The callback function to generate anchor IDs.
     * @return void
     */
    public function setCreateAnchorIDCallback(callable $callback): void
    {
        $this->createAnchorIDCallback = $callback;
    }

    /**
     * Creates an anchor ID for a given header text.
     *
     * This function generates a unique anchor ID for a header, allowing for custom
     * callbacks to be used for the generation logic. If no callback is provided,
     * default logic is used, including transliteration, normalization, and sanitization.
     *
     * @since 1.0.0
     *
     * @param string $text The header text for which an anchor ID is generated.
     * @return string|null The generated anchor ID or null if auto anchors are disabled.
     */
    protected function createAnchorID(string $text): ?string
    {
        // Check if automatic anchor generation is enabled in the settings
        if (!$this->config()->get('headings.auto_anchors')) {
            return null; // Return null if auto anchors are disabled
        }

        // If a user-defined callback is provided, use it to generate the anchor ID
        if (is_callable($this->createAnchorIDCallback)) {
            return call_user_func($this->createAnchorIDCallback, $text, $this->config());
        }

        // Default logic for anchor ID creation

        // Convert text to lowercase if configured to do so
        if ($this->config()->get('headings.auto_anchors.lowercase')) {
            if (extension_loaded('mbstring')) {
                $text = mb_strtolower($text);
            } else {
                $text = strtolower($text);
            }
        }

        // Apply replacements to the text based on the configuration settings
        if ($this->config()->get('headings.auto_anchors.replacements')) {
            $text = preg_replace(array_keys($this->config()->get('headings.auto_anchors.replacements')), $this->config()->get('headings.auto_anchors.replacements'), $text);
        }

        // Normalize the text (ensure proper encoding)
        $text = $this->normalizeString($text);

        // Transliterate text if configured to do so
        if ($this->config()->get('headings.auto_anchors.transliterate')) {
            $text = $this->transliterate($text);
        }

        // Sanitize the text to make it a valid anchor ID
        $text = $this->sanitizeAnchor($text);

        // Ensure the generated anchor ID is unique
        return $this->uniquifyAnchorID($text);
    }

    /**
     * Normalizes the given string to UTF-8 encoding.
     *
     * This function ensures that the given text is properly encoded to UTF-8, using
     * `mb_convert_encoding` if available. If `mbstring` is not available, it returns
     * the raw string as there is no equivalent alternative.
     *
     * @since 1.2.0
     *
     * @param string $text The input string to be normalized.
     * @return string The normalized string.
     */
    protected function normalizeString(string $text)
    {
        if (extension_loaded('mbstring')) {
            return mb_convert_encoding($text, 'UTF-8', mb_list_encodings());
        } else {
            return $text; // Return raw text as there is no good alternative for mb_convert_encoding
        }
    }

    /**
     * Transliterates the given string to ASCII format.
     *
     * This function attempts to transliterate text to ASCII, making it suitable for
     * use in anchor IDs. It uses PHP's `Transliterator` class if available. If not,
     * a manual transliteration method is used as a fallback.
     *
     * @since 1.2.0
     *
     * @param string $text The text to be transliterated.
     * @return string The transliterated text.
     */
    protected function transliterate(string $text): string
    {
        if (class_exists('\Transliterator')) {
            $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII;');
            if ($transliterator) {
                return $transliterator->transliterate($text);
            }
        }

        return $this->manualTransliterate($text); // Use manual transliteration if `Transliterator` is not available
    }



    /**
     * Manually transliterates a string from various alphabets to ASCII.
     *
     * This function converts characters from different scripts (Latin, Greek, Cyrillic, etc.) into their ASCII equivalents.
     * It uses a predefined character map to replace accented or special characters with simpler ASCII versions.
     *
     * @since 1.3.0
     *
     * @param string $text The input text to be transliterated.
     * @return string The transliterated ASCII string.
     */
    protected function manualTransliterate(string $text): string
    {
        // Character mapping from different alphabets to their ASCII equivalents
        $characterMap = [
            // Latin
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'AA', 'Æ' => 'AE', 'Ç' => 'C',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ð' => 'D', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ő' => 'O',
            'Ø' => 'OE', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ű' => 'U', 'Ý' => 'Y', 'Þ' => 'TH',
            'ß' => 'ss',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'aa', 'æ' => 'ae', 'ç' => 'c',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ð' => 'd', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ő' => 'o',
            'ø' => 'oe', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ű' => 'u', 'ý' => 'y', 'þ' => 'th',
            'ÿ' => 'y',

            // Latin symbols
            '©' => '(c)', '®' => '(r)', '™' => '(tm)',

            // Greek
            'Α' => 'A', 'Β' => 'B', 'Γ' => 'G', 'Δ' => 'D', 'Ε' => 'E', 'Ζ' => 'Z', 'Η' => 'H', 'Θ' => 'TH',
            'Ι' => 'I', 'Κ' => 'K', 'Λ' => 'L', 'Μ' => 'M', 'Ν' => 'N', 'Ξ' => 'X', 'Ο' => 'O', 'Π' => 'P',
            'Ρ' => 'R', 'Σ' => 'S', 'Τ' => 'T', 'Υ' => 'Y', 'Φ' => 'F', 'Χ' => 'X', 'Ψ' => 'PS', 'Ω' => 'O',
            'Ά' => 'A', 'Έ' => 'E', 'Ί' => 'I', 'Ό' => 'O', 'Ύ' => 'Y', 'Ή' => 'H', 'Ώ' => 'O', 'Ϊ' => 'I',
            'Ϋ' => 'Y',
            'α' => 'a', 'β' => 'b', 'γ' => 'g', 'δ' => 'd', 'ε' => 'e', 'ζ' => 'z', 'η' => 'h', 'θ' => 'th',
            'ι' => 'i', 'κ' => 'k', 'λ' => 'l', 'μ' => 'm', 'ν' => 'n', 'ξ' => 'x', 'ο' => 'o', 'π' => 'p',
            'ρ' => 'r', 'σ' => 's', 'τ' => 't', 'υ' => 'y', 'φ' => 'f', 'χ' => 'x', 'ψ' => 'ps', 'ω' => 'o',
            'ά' => 'a', 'έ' => 'e', 'ί' => 'i', 'ό' => 'o', 'ύ' => 'y', 'ή' => 'h', 'ώ' => 'o', 'ς' => 's',
            'ϊ' => 'i', 'ΰ' => 'y', 'ϋ' => 'y', 'ΐ' => 'i',

            // Turkish
            'Ş' => 'S', 'İ' => 'I', 'Ğ' => 'G',
            'ş' => 's', 'ı' => 'i', 'ğ' => 'g',

            // Russian
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh',
            'З' => 'Z', 'И' => 'I', 'Й' => 'J', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O',
            'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'Kh', 'Ц' => 'Ts',
            'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Shch', 'Ъ' => 'U', 'Ы' => 'Y', 'Ь' => '', 'Э' => 'E', 'Ю' => 'Yu',
            'Я' => 'Ya',
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo', 'ж' => 'zh',
            'з' => 'z', 'и' => 'i', 'й' => 'j', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
            'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts',
            'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ъ' => 'u', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu',
            'я' => 'ya',

            // Ukrainian
            'Є' => 'Ye', 'І' => 'I', 'Ї' => 'Yi', 'Ґ' => 'G',
            'є' => 'ye', 'і' => 'i', 'ї' => 'yi', 'ґ' => 'g',

            // Czech
            'Č' => 'C', 'Ď' => 'D', 'Ě' => 'E', 'Ň' => 'N', 'Ř' => 'R', 'Š' => 'S', 'Ť' => 'T', 'Ů' => 'U',
            'Ž' => 'Z',
            'č' => 'c', 'ď' => 'd', 'ě' => 'e', 'ň' => 'n', 'ř' => 'r', 'š' => 's', 'ť' => 't', 'ů' => 'u',
            'ž' => 'z',

            // Polish
            'Ą' => 'A', 'Ć' => 'C', 'Ę' => 'E', 'Ł' => 'L', 'Ń' => 'N', 'Ś' => 'S', 'Ź' => 'Z',
            'Ż' => 'Z',
            'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ś' => 's', 'ź' => 'z',
            'ż' => 'z',

            // Latvian
            'Ā' => 'A', 'Ē' => 'E', 'Ģ' => 'G', 'Ī' => 'I', 'Ķ' => 'K', 'Ļ' => 'L', 'Ņ' => 'N', 'Ū' => 'U',
            'ā' => 'a', 'ē' => 'e', 'ģ' => 'g', 'ī' => 'i', 'ķ' => 'k', 'ļ' => 'l', 'ņ' => 'n', 'ū' => 'u',
        ];

        // Perform the character replacements based on the map
        return strtr($text, $characterMap);
    }

    /**
     * Sanitizes a string to make it suitable for use as an HTML anchor ID.
     *
     * This function replaces non-alphanumeric characters in the string with a delimiter
     * (e.g., hyphen), ensuring the result is suitable as an HTML ID. Consecutive delimiters
     * are collapsed into a single delimiter, and leading/trailing delimiters are trimmed.
     *
     * @since 1.2.0
     *
     * @param string $text The input text to be sanitized.
     * @return string The sanitized string suitable for use as an anchor ID.
     */
    protected function sanitizeAnchor(string $text): string
    {
        // Get the delimiter used to replace non-alphanumeric characters (e.g., '-')
        $delimiter = $this->config()->get('headings.auto_anchors.delimiter');

        // Replace any character that is not a letter or number with the delimiter
        $text = preg_replace('/[^\p{L}\p{Nd}]+/u', $delimiter, $text);

        // Collapse consecutive delimiters into a single delimiter
        $text = preg_replace('/(' . preg_quote($delimiter, '/') . '){2,}/', '$1', $text);

        // Trim any leading or trailing delimiters
        return trim($text, $delimiter);
    }

    /**
     * Ensures that the generated anchor ID is unique.
     *
     * This function keeps track of generated anchor IDs to avoid duplicates. If an anchor ID has already been used,
     * it appends a unique suffix to it. Blacklisted anchor IDs are also skipped to ensure the final anchor is valid.
     *
     * @since 1.2.0
     *
     * @param string $text The base anchor ID text.
     * @return string A unique anchor ID.
     */
    protected function uniquifyAnchorID(string $text): string
    {
        // Retrieve the blacklist of forbidden anchor IDs from the configuration
        $blacklist = $this->config()->get('headings.auto_anchors.blacklist');

        // Store the original text to use as the base for creating unique variants
        $originalText = $text;

        // Initialize or increment the counter for this specific anchor text
        if (!isset($this->anchorRegister[$text])) {
            $this->anchorRegister[$text] = 0;
        } else {
            $this->anchorRegister[$text]++;
        }

        // Adjust the anchor ID to ensure it is unique and not in the blacklist
        while (true) {
            // Generate the potential anchor ID with the count as suffix (if needed)
            $potentialId = $originalText . ($this->anchorRegister[$text] > 0 ? '-' . $this->anchorRegister[$text] : '');

            // Check if the potential ID is not blacklisted
            if (!in_array($potentialId, $blacklist)) {
                break; // The ID is valid and not blacklisted, so we can use it
            }

            // Increment the counter to generate the next potential ID
            $this->anchorRegister[$text]++;
        }

        // If no suffix is required, return the original anchor text
        if ($this->anchorRegister[$text] === 0) {
            return $originalText;
        }

        // Return the unique anchor ID with the appropriate suffix
        return $originalText . '-' . $this->anchorRegister[$text];
    }


    /**
     * Decodes the ToC tag by replacing a hashed version with the original tag.
     *
     * This function looks for the hashed ToC tag within the text and replaces it with the original ToC tag,
     * effectively decoding the tag back to its original form.
     *
     * @since 1.2.0
     *
     * @param string $text The input text containing the hashed ToC tag.
     * @return string The text with the hashed ToC tag replaced by the original tag.
     */
    protected function decodeTag(string $text): string
    {
        $salt = $this->getSalt(); // Retrieve the salt used for hashing
        $tag_origin = $this->config()->get('toc.tag'); // Get the original ToC tag
        $tag_hashed = hash('sha256', $salt . $tag_origin); // Generate the hashed version of the ToC tag

        // If the hashed tag is not found, return the original text
        if (strpos($text, $tag_hashed) === false) {
            return $text;
        }

        // Replace the hashed tag with the original tag
        return str_replace($tag_hashed, $tag_origin, $text);
    }

    /**
     * Encodes the ToC tag by replacing it with a hashed version.
     *
     * This function looks for the original ToC tag in the text and replaces it with a hashed version,
     * effectively encoding it to avoid conflicts during parsing.
     *
     * @since 1.2.0
     *
     * @param string $text The input text containing the ToC tag.
     * @return string The text with the original ToC tag replaced by the hashed version.
     */
    protected function encodeTag(string $text): string
    {
        $salt = $this->getSalt(); // Retrieve the salt used for hashing
        $tag_origin = $this->config()->get('toc.tag'); // Get the original ToC tag

        // If the original tag is not found, return the original text
        if (strpos($text, $tag_origin) === false) {
            return $text;
        }

        // Generate the hashed version of the ToC tag and replace the original tag
        $tag_hashed = hash('sha256', $salt . $tag_origin);
        return str_replace($tag_origin, $tag_hashed, $text);
    }

    /**
     * Fetches plain text from a given input by stripping tags.
     *
     * This function parses the given text using line formatting, then strips any HTML tags and trims whitespace,
     * effectively extracting plain text.
     *
     * @since 1.0.0
     *
     * @param string $text The input text to be fetched.
     * @return string The plain text version of the input.
     */
    protected function fetchText($text): string
    {
        return trim(strip_tags($this->line($text)));
    }

    /**
     * Generates or retrieves a salt value for use in hashing.
     *
     * This function generates a unique salt value based on the current timestamp if it hasn't been set yet.
     * The salt is used to create a unique hash for ToC tags, making them harder to predict.
     *
     * @since 1.0.0
     *
     * @return string The generated or retrieved salt value.
     */
    protected function getSalt(): string
    {
        static $salt;
        if (isset($salt)) {
            return $salt; // Return the previously generated salt
        }

        // Generate a new salt based on the current timestamp
        $salt = hash('md5', (string) time());
        return $salt;
    }

    /**
     * Adds an entry to the contents list in both array and string formats.
     *
     * This function stores a representation of the contents as both an array and a formatted string.
     * The array format can be used for structured data, while the string format is used for Markdown.
     *
     * @since 1.0.0
     *
     * @param array $Content The content entry containing 'text', 'id', and 'level' keys.
     * @return void
     */
    protected function setContentsList(array $Content): void
    {
        // Stores content as an array
        $this->setContentsListAsArray($Content);
        // Stores content as a string in Markdown list format
        $this->setContentsListAsString($Content);
    }

    /**
     * Stores the given content entry in the Table of Contents array.
     *
     * This function adds the content entry to the `contentsListArray`, which is used to hold a structured
     * representation of all ToC entries.
     *
     * @since 1.0.0
     *
     * @param array $Content The content entry to be stored.
     * @return void
     */
    protected function setContentsListAsArray(array $Content): void
    {
        $this->contentsListArray[] = $Content; // Append content to the contents list array
    }

    /**
     * Adds the given content entry to the Table of Contents string.
     *
     * This function creates a formatted Markdown list item for the content and appends it to the
     * Table of Contents string, which is used to generate the ToC in Markdown format.
     *
     * @since 1.0.0
     *
     * @param array $Content The content entry containing 'text', 'id', and 'level' keys.
     * @return void
     */
    protected function setContentsListAsString(array $Content): void
    {
        $text = $this->fetchText($Content['text']); // Fetch the plain text of the content
        $id = $Content['id']; // Get the ID of the content
        $level = (int) trim($Content['level'], 'h'); // Get the level of the heading and convert to an integer
        $link = "[{$text}](#{$id})"; // Create a Markdown link to the heading

        // Set the first heading level if it hasn't been set yet
        if ($this->firstHeadLevel === 0) {
            $this->firstHeadLevel = $level;
        }

        // Calculate the indent level for the list item
        $indentLevel = max(1, $level - ($this->firstHeadLevel - 1));
        $indent = str_repeat('  ', $indentLevel); // Create the appropriate indent based on the level

        // Append the formatted list item to the contents list string
        $this->contentsListString .= "{$indent}- {$link}" . PHP_EOL;
    }

    /**
     * Parses the given Markdown text and replaces the ToC tag with the generated Table of Contents.
     *
     * This function calls the `body()` method to parse Markdown, and then replaces the placeholder
     * ToC tag with the generated Table of Contents in HTML format.
     *
     * @since 0.1.0
     *
     * @param string $text The input Markdown text.
     * @return string The parsed HTML text with the ToC embedded.
     */
    public function text($text): string
    {
        $html = $this->body($text); // Parse the Markdown text into HTML

        // If ToC functionality is disabled in the config, return the parsed HTML as is
        if (!$this->config()->get('toc')) {
            return $html;
        }

        // Get the original ToC tag and check if it is in the input text
        $tag_origin = $this->config()->get('toc.tag');
        if (strpos($text, $tag_origin) === false) {
            return $html; // Return HTML if the ToC tag is not found
        }

        // Replace the ToC placeholder with the actual ToC content
        $toc_data = $this->contentsList();
        $toc_id = $this->config()->get('toc.id');
        return str_replace("<p>{$tag_origin}</p>", "<div id=\"{$toc_id}\">{$toc_data}</div>", $html);
    }

    /**
     * Processes unmarked text, adding predefined abbreviations if configured.
     *
     * This function extends the parent class's functionality by adding predefined
     * abbreviations from the configuration, before processing the unmarked text.
     *
     * @since 0.1.0
     *
     * @param string $text The input text to be processed.
     * @return string The processed text with abbreviations applied.
     */
    protected function unmarkedText($text)
    {
        // Add predefined abbreviations to the definition data
        foreach ($this->config()->get('abbreviations.predefined') as $abbreviation => $description) {
            $this->DefinitionData['Abbreviation'][$abbreviation] = $description;
        }

        // Call the parent method to handle the rest of the text processing
        return parent::unmarkedText($text);
    }


    // Settings
    // -------------------------------------------------------------------------

    /**
     * Sets a configuration setting (DEPRECATED).
     *
     * This method sets a configuration setting using the new configuration system.
     * It is deprecated and will be removed in future versions. Use `$ParsedownExtended->config()->set()` instead.
     *
     * @since 1.2.0
     * @deprecated 1.3.0 Use ParsedownExtended->config()->set() instead.
     * @see ParsedownExtended->config()->set()
     *
     * @param string $settingName The name of the setting to set.
     * @param mixed $value The value to set for the setting.
     * @param bool $overwrite Whether to overwrite an existing setting (default: false).
     * @return void
     */
    public function setSetting(string $settingName, $value, bool $overwrite = false)
    {
        // Log the use of deprecated method for future reference
        $this->deprecated(__METHOD__, '1.3.0', '$ParsedownExtended->config()->set()');

        // Use the new configuration system to set the value
        $this->config()->set($settingName, $value);
    }

    /**
     * Sets multiple configuration settings at once (DEPRECATED).
     *
     * This method sets multiple configuration settings using the new configuration system.
     * It is deprecated and will be removed in future versions. Use `$ParsedownExtended->config()->set()` instead.
     *
     * @since 1.2.0
     * @deprecated 1.3.0 Use ParsedownExtended->config()->set() instead.
     * @see ParsedownExtended->config()->set()
     *
     * @param array $settings An associative array of settings to set (key-value pairs).
     * @return $this
     */
    public function setSettings(array $settings)
    {
        // Log the use of deprecated method for future reference
        $this->deprecated(__METHOD__, '1.3.0', '$ParsedownExtended->config()->set()');

        // Set each individual setting using the existing setSetting method
        foreach ($settings as $key => $value) {
            $this->setSetting($key, $value);
        }

        return $this;
    }

    /**
     * Checks if a configuration setting is enabled (DEPRECATED).
     *
     * This method checks if a particular setting is enabled using the new configuration system.
     * It is deprecated and will be removed in future versions. Use `$ParsedownExtended->config()->get()` instead.
     *
     * @since 1.2.2
     * @deprecated 1.3.0 Use ParsedownExtended->config()->get() instead.
     * @see ParsedownExtended->config()->get()
     *
     * @param string $keyPath The key path of the setting to check.
     * @return mixed The value of the setting (generally a boolean for 'enabled' settings).
     */
    public function isEnabled(string $keyPath)
    {
        // Log the use of deprecated method for future reference
        $this->deprecated(__METHOD__, '1.3.0', '$ParsedownExtended->config()->get()');

        // Use the new configuration system to get the value
        return $this->config()->get($keyPath);
    }

    /**
     * Gets the value of a configuration setting (DEPRECATED).
     *
     * This method retrieves a setting using the new configuration system.
     * It is deprecated and will be removed in future versions. Use `$ParsedownExtended->config()->get()` instead.
     *
     * @since 1.2.0
     * @deprecated 1.3.0 Use ParsedownExtended->config()->get() instead.
     * @see ParsedownExtended->config()->get()
     *
     * @param string $key The key of the setting to retrieve.
     * @return mixed The value of the specified setting.
     */
    public function getSetting(string $key)
    {
        // Log the use of deprecated method for future reference
        $this->deprecated(__METHOD__, '1.3.0', '$ParsedownExtended->config()->get()');

        // Use the new configuration system to get the value
        return $this->config()->get($key);
    }

    /**
     * Gets all configuration settings (DEPRECATED).
     *
     * This method retrieves all settings.
     * It is deprecated and will be removed in future versions. Use `$ParsedownExtended->config()->get()` instead.
     *
     * @since 1.2.0
     * @deprecated 1.3.0 Use ParsedownExtended->config()->get() instead.
     * @see ParsedownExtended->config()->get()
     *
     * @return array An associative array of all settings.
     */
    public function getSettings()
    {
        // Log the use of deprecated method for future reference
        $this->deprecated(__METHOD__, '1.3.0', '$ParsedownExtended->config()->get()');

        // Return the current settings
        return $this->settings;
    }


    // Helper functions
    // -------------------------------------------------------------------------

    /**
     * Registers an inline type marker with a corresponding handler function.
     *
     * This function ensures that a given marker is registered for inline parsing, associating it with
     * a handler function that will handle the inline behavior for that marker.
     *
     * @since 1.1.2
     *
     * @param mixed $markers One or more markers to register (can be a string or an array).
     * @param string $funcName The name of the handler function associated with the marker(s).
     * @return void
     */
    private function addInlineType($markers, string $funcName): void
    {
        // Ensure $markers is always an array, even if a single marker is passed as a string
        $markers = (array) $markers;

        foreach ($markers as $marker) {
            // If the marker is not already registered, initialize it
            if (!isset($this->InlineTypes[$marker])) {
                $this->InlineTypes[$marker] = [];
            }

            // Add the marker to the special characters array if it's not already present
            if (!in_array($marker, $this->specialCharacters)) {
                $this->specialCharacters[] = $marker;
            }

            // Add the function name to the beginning of the marker's handlers for priority
            array_unshift($this->InlineTypes[$marker], $funcName);

            // Append the marker to the inline marker list
            $this->inlineMarkerList .= $marker;
        }
    }

    /**
     * Registers a block type marker with a corresponding handler function.
     *
     * This function ensures that a given marker is registered for block parsing, associating it with
     * a handler function that will handle the block behavior for that marker.
     *
     * @since 1.1.2
     *
     * @param mixed $markers One or more markers to register (can be a string or an array).
     * @param string $funcName The name of the handler function associated with the marker(s).
     * @return void
     */
    private function addBlockType($markers, string $funcName): void
    {
        // Ensure $markers is always an array, even if a single marker is passed as a string
        $markers = (array) $markers;

        foreach ($markers as $marker) {
            // If the marker is not already registered, initialize it
            if (!isset($this->BlockTypes[$marker])) {
                $this->BlockTypes[$marker] = [];
            }

            // Add the marker to the special characters array if it's not already present
            if (!in_array($marker, $this->specialCharacters)) {
                $this->specialCharacters[] = $marker;
            }

            // Add the function name to the beginning of the marker's handlers for priority
            array_unshift($this->BlockTypes[$marker], $funcName);
        }
    }

    /**
     * Warns users about deprecated functions.
     *
     * This function is used to trigger a deprecation warning when deprecated functions are called.
     * It informs the user about the function being deprecated, the version it was deprecated in,
     * and suggests an alternative function to use.
     *
     * @since 1.3.0
     *
     * @param string $functionName The name of the deprecated function.
     * @param string $version The version in which the function was deprecated.
     * @param string $alternative (Optional) The name of an alternative function to use.
     * @return void
     */
    private function deprecated(string $functionName, string $version, string $alternative = ''): void
    {
        // Get the call stack to determine where this deprecated function was called
        $backtrace = debug_backtrace();
        $caller = $backtrace[1] ?? $backtrace[0];

        // Create the deprecation message with the function name and version
        $message = "Function {$functionName} is deprecated as of version {$version} and will be removed in the future. ";
        // Append an alternative function suggestion if provided
        $message .= $alternative ? "Use {$alternative} instead." : '';
        // Include the file and line number where the deprecated function was called
        $message .= " Called in {$caller['file']} on line {$caller['line']}";

        // Trigger the deprecated warning
        trigger_error($message, E_USER_DEPRECATED);
    }

    // Configurations Handler
    // -------------------------------------------------------------------------

    /**
     * Initialize configuration using a given schema.
     *
     * This function iterates through the given schema to initialize the default configuration settings.
     * It handles nested arrays and array types with nested defaults.
     *
     * @since 1.3.0
     *
     * @param array $schema The configuration schema to use for initialization.
     * @return array The initialized configuration based on the given schema.
     */
    private function initializeConfig(array $schema)
    {
        $config = [];
        foreach ($schema as $key => $definition) {
            // Handle array types with nested defaults
            if (isset($definition['type'])) {
                if ($definition['type'] === 'array' && is_array($definition['default'])) {
                    $config[$key] = $this->initializeConfig($definition['default']);
                } else {
                    $config[$key] = $definition['default'];
                }
            } else {
                // Recursively initialize nested configurations
                if (is_array($definition)) {
                    $config[$key] = $this->initializeConfig($definition);
                } else {
                    $config[$key] = $definition;
                }
            }
        }
        return $config;
    }

    /**
     * Define the configuration schema.
     *
     * This function returns a comprehensive configuration schema that defines the type,
     * default values, and nested structures for each configuration setting.
     *
     * @since 1.3.0
     *
     * @return array The defined configuration schema.
     */
    private function defineConfigSchema(): array
    {
        return [
            'abbreviations' => [
                'enabled' => ['type' => 'boolean', 'default' => true],
                'allow_custom' => ['type' => 'boolean', 'default' => true],
                'predefined' => [
                    'type' => 'array',
                    'default' => [],
                    'item_schema' => [
                        'key_type' => 'string',
                        'value_type' => 'string',
                    ],
                ],
            ],
            'code' => [
                'enabled' => ['type' => 'boolean', 'default' => true],
                'blocks' => ['type' => 'boolean', 'default' => true],
                'inline' => ['type' => 'boolean', 'default' => true],
            ],
            'comments' => ['type' => 'boolean', 'default' => true],
            'definition_lists' => ['type' => 'boolean', 'default' => true],
            'diagrams' => [
                'enabled' => ['type' => 'boolean', 'default' => false],
                'chartjs' => ['type' => 'boolean', 'default' => true],
                'mermaid' => ['type' => 'boolean', 'default' => true],
            ],
            'emojis' => ['type' => 'boolean', 'default' => true],
            'emphasis' => [
                'enabled' => ['type' => 'boolean', 'default' => true],
                'bold' => ['type' => 'boolean', 'default' => true],
                'italic' => ['type' => 'boolean', 'default' => true],
                'strikethroughs' => ['type' => 'boolean', 'default' => true],
                'insertions' => ['type' => 'boolean', 'default' => true],
                'subscript' => ['type' => 'boolean', 'default' => false],
                'superscript' => ['type' => 'boolean', 'default' => false],
                'keystrokes' => ['type' => 'boolean', 'default' => true],
                'mark' => ['type' => 'boolean', 'default' => true],
            ],
            'footnotes' => ['type' => 'boolean', 'default' => true],
            'headings' => [
                'enabled' => ['type' => 'boolean', 'default' => true],
                'allowed_levels' => ['type' => 'array', 'default' => ['h1', 'h2', 'h3', 'h4', 'h5', 'h6']],
                'auto_anchors' => [
                    'enabled' => ['type' => 'boolean', 'default' => true],
                    'delimiter' => ['type' => 'string', 'default' => '-'],
                    'lowercase' => ['type' => 'boolean', 'default' => true],
                    'replacements' => ['type' => 'array', 'default' => []],
                    'transliterate' => ['type' => 'boolean', 'default' => false],
                    'blacklist' => ['type' => 'array', 'default' => []],
                ],
                'special_attributes' => ['type' => 'boolean', 'default' => true],
            ],
            'images' => ['type' => 'boolean', 'default' => true],
            'links' => [
                'enabled' => ['type' => 'boolean', 'default' => true],
                'email_links' => ['type' => 'boolean', 'default' => true],
                'external_links' => [
                    'enabled' => ['type' => 'boolean', 'default' => true],
                    'nofollow' => ['type' => 'boolean', 'default' => true],
                    'noopener' => ['type' => 'boolean', 'default' => true],
                    'noreferrer' => ['type' => 'boolean', 'default' => true],
                    'open_in_new_window' => ['type' => 'boolean', 'default' => true],
                    'internal_hosts' => [
                        'type' => 'array', 'default' => [],
                        'item_schema' => ['type' => 'string'],
                    ],
                ],
            ],
            'lists' => [
                'enabled' => ['type' => 'boolean', 'default' => true],
                'tasks' => ['type' => 'boolean', 'default' => true],
            ],
            'allow_raw_html' => ['type' => 'boolean', 'default' => true],
            'alerts' => [
                'enabled' => ['type' => 'boolean', 'default' => true],
                'types' => [
                    'type' => 'array',
                    'default' => ['note', 'tip', 'important', 'warning', 'caution'],
                    'item_schema' => ['type' => 'string'],
                ],
                'class' => ['type' => 'string', 'default' => 'markdown-alert'],
            ],
            'math' => [
                'enabled' => ['type' => 'boolean', 'default' => false],
                'inline' => [
                    'enabled' => ['type' => 'boolean', 'default' => true],
                    'delimiters' => [
                        'type' => 'array',
                        'default' => [['left' => '$', 'right' => '$']],
                        'item_schema' => ['type' => 'array', 'keys' => ['left' => 'string', 'right' => 'string']],
                    ],
                ],
                'block' => [
                    'enabled' => ['type' => 'boolean', 'default' => true],
                    'delimiters' => [
                        'type' => 'array',
                        'default' => [
                            ['left' => '$$', 'right' => '$$'],
                        ],
                        'item_schema' => ['type' => 'array', 'keys' => ['left' => 'string', 'right' => 'string']],
                    ],
                ],
            ],
            'quotes' => ['type' => 'boolean', 'default' => true],
            'references' => ['type' => 'boolean', 'default' => true],
            'smartypants' => [
                'enabled' => ['type' => 'boolean', 'default' => false],
                'smart_angled_quotes' => ['type' => 'boolean', 'default' => true],
                'smart_backticks' => ['type' => 'boolean', 'default' => true],
                'smart_dashes' => ['type' => 'boolean', 'default' => true],
                'smart_ellipses' => ['type' => 'boolean', 'default' => true],
                'smart_quotes' => ['type' => 'boolean', 'default' => true],
                'substitutions' => [
                    'ellipses' => ['type' => 'string', 'default' => '&hellip;'],
                    'left_angle_quote' => ['type' => 'string', 'default' => '&laquo;'],
                    'left_double_quote' => ['type' => 'string', 'default' => '&ldquo;'],
                    'left_single_quote' => ['type' => 'string', 'default' => '&lsquo;'],
                    'mdash' => ['type' => 'string', 'default' => '&mdash;'],
                    'ndash' => ['type' => 'string', 'default' => '&ndash;'],
                    'right_angle_quote' => ['type' => 'string', 'default' => '&raquo;'],
                    'right_double_quote' => ['type' => 'string', 'default' => '&rdquo;'],
                    'right_single_quote' => ['type' => 'string', 'default' => '&rsquo;'],
                ],
            ],
            'tables' => [
                'enabled' => ['type' => 'boolean', 'default' => true],
                'tablespan' => ['type' => 'boolean', 'default' => true],
            ],
            'thematic_breaks' => ['type' => 'boolean', 'default' => true],
            'toc' => [
                'enabled' => ['type' => 'boolean', 'default' => true],
                'levels' => [
                    'type' => 'array',
                    'default' => ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'],
                    'item_schema' => ['type' => 'string'],
                ],
                'tag' => ['type' => 'string', 'default' => '[TOC]'],
                'id' => ['type' => 'string', 'default' => 'toc'],
            ],
            'typographer' => ['type' => 'boolean', 'default' => true],
        ];
    }


    /**
     * Retrieve the configuration schema.
     *
     * This function returns the complete configuration schema that defines the structure,
     * expected data types, and default values for all configurable settings.
     *
     * The schema is used internally for validation and providing type safety when getting or setting configuration values.
     *
     * @since 1.3.0
     *
     * @return array The configuration schema as an associative array.
     */
    public function getConfigSchema(): array
    {
        return $this->configSchema;
    }

    /**
     * Return a new instance of an anonymous configuration class.
     *
     * This function creates an instance of a class that provides methods to interact with the configuration settings.
     * It allows getting and setting configuration values, including translating deprecated keys and validating types.
     *
     * @since 1.3.0
     *
     * @return object Anonymous configuration object with get and set methods.
     */
    public function config()
    {
        return new class ($this->configSchema, $this->config) {
            private array $schema;
            private $config;

            /**
             * Constructor to initialize configuration schema and reference configuration array.
             *
             * @since 0.1.0
             *
             * @param array $schema The schema that defines the structure and types of config.
             * @param array &$config A reference to the actual configuration array.
             */
            public function __construct(array $schema, &$config)
            {
                $this->schema = $schema;
                $this->config = &$config;
            }

            /**
             * Translate deprecated key paths to the new key paths.
             *
             * This function checks for deprecated configuration keys and suggests a newer version if available.
             *
             * @since 1.3.0
             *
             * @param string $keyPath The key path to be translated.
             * @return string The translated or original key path.
             */
            private function translateDeprecatedKeyPath(string $keyPath): string
            {
                static $deprecatedMapping = [
                    // Mapping of deprecated keys to new keys.
                    'abbreviations.allow_custom_abbr' => 'abbreviations.allow_custom',
                    'abbreviations.predefine' => 'abbreviations.predefined',
                    'emphasis.marking' => 'emphasis.mark',
                    'headings.allowed' => 'headings.allowed_levels',
                    'smarty' => 'smartypants',
                    'smarty.substitutions.left-angle-quote' => 'smartypants.substitutions.left_angle_quote',
                    'toc.toc_tag' => 'toc.tag',
                    'markup' => 'allow_raw_html',
                    'toc.headings' => 'toc.levels',
                ];

                // If the key path is deprecated, trigger a deprecation warning.
                if (isset($deprecatedMapping[$keyPath])) {
                    $backtrace = debug_backtrace();
                    $caller = $backtrace[1] ?? $backtrace[0];
                    $message = "The config path '{$keyPath}' is deprecated. Use '{$deprecatedMapping[$keyPath]}' instead. Called in " . ($caller['file'] ?? 'unknown') . " on line " . ($caller['line'] ?? 'unknown');
                    trigger_error($message, E_USER_DEPRECATED);
                }

                return $deprecatedMapping[$keyPath] ?? $keyPath;
            }

            /**
             * Retrieves a value from a nested array or object using a dot-separated key path.
             *
             * @since 1.3.0
             *
             * @param string $keyPath Dot-separated key path indicating the config to get.
             * @param bool $raw Whether to return the raw value without any processing.
             * @return mixed The value of the configuration setting.
             * @throws \InvalidArgumentException If the key path is invalid.
             */
            public function get(string $keyPath, bool $raw = false)
            {
                // Translate deprecated key paths.
                $keyPath = $this->translateDeprecatedKeyPath($keyPath);

                // Split the key path into individual keys.
                $keys = explode('.', $keyPath);
                $value = $this->config;

                // Traverse through keys to reach the desired value.
                foreach ($keys as $key) {
                    if (!array_key_exists($key, $value)) {
                        $backtrace = debug_backtrace();
                        $caller = $backtrace[1] ?? $backtrace[0];
                        $errorMessage = "Invalid key path '{$keyPath}' given. Called in " . ($caller['file'] ?? 'unknown') . " on line " . ($caller['line'] ?? 'unknown');
                        throw new \InvalidArgumentException($errorMessage);
                    }
                    $value = $value[$key];
                }

                if ($raw) {
                    return $value;
                }

                // If the value is an array with an 'enabled' key, return that instead.
                return is_array($value) && isset($value['enabled']) ? $value['enabled'] : $value;
            }

            /**
             * Set the configuration value for the provided key path.
             *
             * @since 1.3.0
             *
             * @param string|array $keyPath Dot-separated key path indicating the config to set or an associative array of key paths and values.
             * @param mixed $value The value to set.
             * @return self Returns the instance for method chaining.
             * @throws \InvalidArgumentException If the key path is invalid or the value is of the wrong type.
             */
            public function set($keyPath, $value = null): self
            {
                if (is_array($keyPath)) {
                    // Set multiple values if an associative array is provided.
                    foreach ($keyPath as $key => $val) {
                        $this->set($key, $val);
                    }
                    return $this;
                }

                // Translate deprecated key paths.
                $keyPath = $this->translateDeprecatedKeyPath($keyPath);

                // Split the key path into individual keys.
                $keys = explode('.', $keyPath);
                $lastKey = array_pop($keys);

                $current = &$this->config;
                $currentSchema = $this->schema;

                // Navigate to the desired configuration section.
                foreach ($keys as $key) {
                    if (!isset($current[$key])) {
                        $backtrace = debug_backtrace();
                        $caller = $backtrace[1] ?? $backtrace[0];
                        $errorMessage = "Invalid key path '{$keyPath}' given. Called in " . ($caller['file'] ?? 'unknown') . " on line " . ($caller['line'] ?? 'unknown');
                        throw new \InvalidArgumentException($errorMessage);
                    }
                    $current = &$current[$key];
                    if (!isset($currentSchema[$key])) {
                        throw new \InvalidArgumentException("Invalid schema path: " . implode('.', $keys));
                    }
                    $currentSchema = $currentSchema[$key];
                }

                // Validate and set the value for the specified key.
                if (isset($currentSchema['default'][$lastKey])) {
                    $expectedType = $currentSchema['default'][$lastKey]['type'];
                    $this->validateType($value, $expectedType, $currentSchema['default'][$lastKey]);
                    $current[$lastKey] = $value;
                } else {
                    if (!isset($currentSchema[$lastKey])) {
                        $backtrace = debug_backtrace();
                        $caller = $backtrace[1] ?? $backtrace[0];
                        $errorMessage = "Invalid key path '{$keyPath}' given. Called in " . ($caller['file'] ?? 'unknown') . " on line " . ($caller['line'] ?? 'unknown');
                        throw new \InvalidArgumentException($errorMessage);
                    }
                    $expectedType = $currentSchema[$lastKey]['type'] ?? null;
                    if ($expectedType) {
                        $this->validateType($value, $expectedType, $currentSchema[$lastKey]);
                    }
                    // Update the 'enabled' field if applicable.
                    if (isset($current[$lastKey]['enabled']) && is_array($current[$lastKey])) {

                        /**
                         * If the value is an array, it recursively sets each sub-value.
                         * Otherwise, it sets the 'enabled' key of the current configuration.
                         */
                        if (is_array($value)) {
                            foreach ($value as $subKey => $subValue) {
                                $this->set($keyPath . '.' . $subKey, $subValue);
                            }
                        } else {
                            $current[$lastKey]['enabled'] = $value;
                        }

                    } else {
                        $current[$lastKey] = $value;
                    }
                }

                return $this;
            }

            /**
             * Validate the type of the given value against the expected type.
             *
             * @since 1.3.0
             *
             * @param mixed $value The value to be validated.
             * @param string $expectedType The expected type of the value.
             * @param array|null $schema Additional schema for validation (e.g., item schema for arrays).
             * @throws \InvalidArgumentException If the value type does not match the expected type.
             */
            protected function validateType($value, string $expectedType, ?array $schema = null): void
            {
                $type = gettype($value);

                if ($expectedType === 'array' && $type === 'array') {
                    if (isset($schema['item_schema'])) {
                        if (isset($schema['item_schema']['key_type']) && isset($schema['item_schema']['value_type'])) {
                            // Validate key-value pairs in the array.
                            $keyType = $schema['item_schema']['key_type'];
                            $valueType = $schema['item_schema']['value_type'];

                            foreach ($value as $key => $item) {
                                if (gettype($key) !== $keyType || gettype($item) !== $valueType) {
                                    $backtrace = debug_backtrace();
                                    $caller = $backtrace[1] ?? $backtrace[0];
                                    $errorMessage = "Array keys must be of type '$keyType' and values of type '$valueType'. Called in " . ($caller['file'] ?? 'unknown') . " on line " . ($caller['line'] ?? 'unknown');
                                    throw new \InvalidArgumentException($errorMessage);
                                }
                            }
                            return;
                        }
                    }
                    return;
                }

                // If types do not match, throw an error with debug information.
                if ($type !== $expectedType) {
                    $backtrace = debug_backtrace();
                    $caller = $backtrace[1] ?? $backtrace[0];
                    $errorMessage = "Expected type $expectedType, got $type. Called in " . ($caller['file'] ?? 'unknown') . " on line " . ($caller['line'] ?? 'unknown');
                    throw new \InvalidArgumentException($errorMessage);
                }
            }
        };
    }


    // Overwriting core Parsedown functions
    // -------------------------------------------------------------------------

    /**
     * Handle an element based on the legacy mode.
     *
     * This function extends the core Parsedown behavior to handle specific cases
     * when in legacy mode, particularly for empty element names.
     *
     * @since 0.1.0
     *
     * @param array $Element The element to be processed.
     * @return string|array Processed element or markup.
     */
    protected function element(array $Element)
    {
        if ($this->legacyMode) {
            // If the element's name is empty, return the text attribute
            if (empty($Element['name'])) {
                return $Element['text'] ?? '';
            }
        }

        // Use the original element method from the parent
        return parent::element($Element);
    }

    /**
     * Process a line of Markdown text and extract inline elements.
     *
     * This function processes a line of Markdown text by iteratively searching for
     * markers in the text, and applies the appropriate inline handlers for those markers.
     *
     * @since 0.1.0
     *
     * @param string $text The text to be parsed for inline elements.
     * @param array $nonNestables Array of inline types that should not be nested.
     * @return string The parsed HTML markup for the given line.
     */
    public function line($text, $nonNestables = [])
    {
        $markup = '';

        // Search for inline markers in the text
        while ($Excerpt = strpbrk((string)$text, $this->inlineMarkerList)) {
            $marker = $Excerpt[0];
            $markerPosition = strpos($text, $marker);

            // Get the character before the marker
            $before = $markerPosition > 0 ? $text[$markerPosition - 1] : '';

            // Create an excerpt array with context for inline processing
            $Excerpt = [
                'text' => $Excerpt,
                'context' => $text,
                'before' => $before,
                'parent' => $this,
            ];

            // Iterate through possible inline types for the marker
            foreach ($this->InlineTypes[$marker] as $inlineType) {
                if (!empty($nonNestables) && in_array($inlineType, $nonNestables)) {
                    continue; // Skip non-nestable inline types in this context
                }

                // Attempt to create an inline element using the handler
                $Inline = $this->{'inline'.$inlineType}($Excerpt);

                if (!isset($Inline)) {
                    continue; // If no inline element was found, continue to the next type
                }

                if (isset($Inline['position']) && $Inline['position'] > $markerPosition) {
                    continue; // Ensure the inline belongs to the current marker
                }

                // Set a default position if not provided
                if (!isset($Inline['position'])) {
                    $Inline['position'] = $markerPosition;
                }

                // Add non-nestables to the inline element
                foreach ($nonNestables as $non_nestable) {
                    $Inline['element']['nonNestables'][] = $non_nestable;
                }

                // Compile the text that comes before the inline element
                $unmarkedText = substr($text, 0, $Inline['position']);
                $markup .= $this->unmarkedText($unmarkedText);

                // Compile the inline element
                $markup .= $Inline['markup'] ?? $this->element($Inline['element']);

                // Remove the processed text from the input
                $text = substr($text, $Inline['position'] + $Inline['extent']);

                continue 2; // Continue parsing the rest of the text
            }

            // If no valid inline marker was found, add the marker to the markup
            $unmarkedText = substr($text, 0, $markerPosition + 1);
            $markup .= $this->unmarkedText($unmarkedText);
            $text = substr($text, $markerPosition + 1);
        }

        // Compile the remaining text
        $markup .= $this->unmarkedText($text);

        return $markup;
    }


    /**
     * Parses a line of text into inline elements.
     *
     * This function processes the given text, identifying markers and breaking it into inline elements.
     * Inline elements include things like bold, italic, links, etc. It recursively handles nesting and respects
     * non-nestable contexts.
     *
     * lineElements() is 1.8 version of line() from 1.7
     *
     * @since 0.1.0
     *
     * @param string $text The text to be parsed.
     * @param array $nonNestables An array of inline types that should not be nested within this context.
     *
     * @return array An array of parsed elements representing the structure of the given text.
     */
    protected function lineElements($text, $nonNestables = []): array
    {
        $Elements = [];

        // If non-nestable elements are provided, convert them to associative array for fast lookup
        $nonNestables = (
            empty($nonNestables)
            ? []
            : array_combine($nonNestables, $nonNestables)
        );

        // $Excerpt represents the first occurrence of an inline marker in the text
        while ($Excerpt = strpbrk($text, $this->inlineMarkerList)) {
            $marker = $Excerpt[0]; // The detected marker
            $markerPosition = strlen($text) - strlen($Excerpt); // Calculate the marker position in the text

            // Get the character before the marker (if any)
            $before = $markerPosition > 0 ? $text[$markerPosition - 1] : '';

            // Prepare an excerpt for further processing
            $Excerpt = ['text' => $Excerpt, 'context' => $text, 'before' => $before];

            // Process all inline types associated with this marker
            foreach ($this->InlineTypes[$marker] as $inlineType) {
                // Skip inline types that are non-nestable within this context
                if (isset($nonNestables[$inlineType])) {
                    continue;
                }

                // Call the corresponding inline processing function
                $Inline = $this->{"inline$inlineType"}($Excerpt);

                // If no valid inline element was found, continue to the next inline type
                if (!isset($Inline)) {
                    continue;
                }

                // Ensure the inline element belongs to the current marker
                if (isset($Inline['position']) && $Inline['position'] > $markerPosition) {
                    continue;
                }

                // Set default inline position if not specified
                if (!isset($Inline['position'])) {
                    $Inline['position'] = $markerPosition;
                }

                // Inherit non-nestable elements from the current context
                $Inline['element']['nonNestables'] = isset($Inline['element']['nonNestables'])
                    ? array_merge($Inline['element']['nonNestables'], $nonNestables)
                    : $nonNestables;

                // Get the text before the inline marker
                $unmarkedText = substr($text, 0, $Inline['position']);

                // Process and add the unmarked text as an element
                $InlineText = $this->inlineText($unmarkedText);
                $Elements[] = $InlineText['element'];

                // Process and add the inline element
                $Elements[] = $this->extractElement($Inline);

                // Remove the processed portion from the text and continue parsing
                $text = substr($text, $Inline['position'] + $Inline['extent']);

                continue 2;
            }

            // If no valid inline element was found for the marker, treat it as plain text
            $unmarkedText = substr($text, 0, $markerPosition + 1);

            // Process and add the unmarked text as an element
            $InlineText = $this->inlineText($unmarkedText);
            $Elements[] = $InlineText['element'];

            // Remove the processed portion from the text
            $text = substr($text, $markerPosition + 1);
        }

        // Process any remaining text after all markers
        $InlineText = $this->inlineText($text);
        $Elements[] = $InlineText['element'];

        // Set the `autobreak` property for each element, defaulting to false if not already set
        foreach ($Elements as &$Element) {
            if (!isset($Element['autobreak'])) {
                $Element['autobreak'] = false;
            }
        }

        return $Elements;
    }
}

