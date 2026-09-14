<?php declare(strict_types = 1);

// osfsl-C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../dompdf/dompdf/src/Options.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Dompdf\Options
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-0d75f8af136c80ba949355fba06109688f1ad46fbc932fb2a8d2bb22f41a020e-8.3.33-6.70.0.6',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Dompdf\\Options',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../dompdf/dompdf/src/Options.php',
      ),
    ),
    'namespace' => 'Dompdf',
    'name' => 'Dompdf\\Options',
    'shortName' => 'Options',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 9,
    'endLine' => 1276,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'rootDir' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'rootDir',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The root of your DOMPDF installation
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 16,
        'endLine' => 16,
        'startColumn' => 5,
        'endColumn' => 21,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'tempDir' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'tempDir',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The location of a temporary directory.
 *
 * The directory specified must be writable by the executing process.
 * The temporary directory is required to download remote images and when
 * using the PFDLib back end.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 27,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 21,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'fontDir' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'fontDir',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The location of the DOMPDF font directory
 *
 * The location of the directory where DOMPDF will store fonts and font metrics
 * Note: This directory must exist and be writable by the executing process.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 37,
        'endLine' => 37,
        'startColumn' => 5,
        'endColumn' => 21,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'fontCache' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'fontCache',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The location of the DOMPDF font cache directory
 *
 * This directory contains the cached font metrics for the fonts used by DOMPDF.
 * This directory can be the same as $fontDir
 *
 * Note: This directory must exist and be writable by the executing process.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 49,
        'endLine' => 49,
        'startColumn' => 5,
        'endColumn' => 23,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'chroot' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'chroot',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * dompdf\'s "chroot"
 *
 * Utilized by Dompdf\'s default file:// protocol URI validation rule.
 * All local files opened by dompdf must be in a subdirectory of the directory
 * or directories specified by this option.
 * DO NOT set this value to \'/\' since this could allow an attacker to use dompdf to
 * read any files on the server.  This should be an absolute path.
 *
 * ==== IMPORTANT ====
 * This setting may increase the risk of system exploit. Do not change
 * this settings without understanding the consequences. Additional
 * documentation is available on the dompdf wiki at:
 * https://github.com/dompdf/dompdf/wiki
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 68,
        'endLine' => 68,
        'startColumn' => 5,
        'endColumn' => 20,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'allowedProtocols' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'allowedProtocols',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '["data://" => ["rules" => []], "file://" => ["rules" => []], "http://" => ["rules" => []], "https://" => ["rules" => []]]',
          'attributes' => 
          array (
            'startLine' => 80,
            'endLine' => 85,
            'startTokenPos' => 57,
            'startFilePos' => 2313,
            'endTokenPos' => 114,
            'endFilePos' => 2471,
          ),
        ),
        'docComment' => '/**
 * Protocol whitelist
 *
 * Protocols and PHP wrappers allowed in URIs, and the validation rules
 * that determine if a resource may be loaded. Full support is not guaranteed
 * for the protocols/wrappers specified
 * by this array.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 80,
        'endLine' => 85,
        'startColumn' => 5,
        'endColumn' => 6,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'artifactPathValidation' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'artifactPathValidation',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 92,
            'endLine' => 92,
            'startTokenPos' => 125,
            'startFilePos' => 2626,
            'endTokenPos' => 125,
            'endFilePos' => 2629,
          ),
        ),
        'docComment' => '/**
 * Operational artifact (log files, temporary files) path validation
 *
 * @var callable
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 92,
        'endLine' => 92,
        'startColumn' => 5,
        'endColumn' => 43,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'logOutputFile' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'logOutputFile',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'\'',
          'attributes' => 
          array (
            'startLine' => 97,
            'endLine' => 97,
            'startTokenPos' => 136,
            'startFilePos' => 2697,
            'endTokenPos' => 136,
            'endFilePos' => 2698,
          ),
        ),
        'docComment' => '/**
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 97,
        'endLine' => 97,
        'startColumn' => 5,
        'endColumn' => 32,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'defaultMediaType' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'defaultMediaType',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '"screen"',
          'attributes' => 
          array (
            'startLine' => 106,
            'endLine' => 106,
            'startTokenPos' => 147,
            'startFilePos' => 2966,
            'endTokenPos' => 147,
            'endFilePos' => 2973,
          ),
        ),
        'docComment' => '/**
 * Styles targeted to this media type are applied to the document.
 * This is on top of the media types that are always applied:
 *    all, static, visual, bitmap, paged, dompdf
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 106,
        'endLine' => 106,
        'startColumn' => 5,
        'endColumn' => 41,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'defaultPaperSize' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'defaultPaperSize',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '"letter"',
          'attributes' => 
          array (
            'startLine' => 116,
            'endLine' => 116,
            'startTokenPos' => 158,
            'startFilePos' => 3233,
            'endTokenPos' => 158,
            'endFilePos' => 3240,
          ),
        ),
        'docComment' => '/**
 * The default paper size.
 *
 * North America standard is "letter"; other countries generally "a4"
 * @see \\Dompdf\\Adapter\\CPDF::PAPER_SIZES for valid sizes
 *
 * @var string|float[]
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 116,
        'endLine' => 116,
        'startColumn' => 5,
        'endColumn' => 41,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'defaultPaperOrientation' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'defaultPaperOrientation',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '"portrait"',
          'attributes' => 
          array (
            'startLine' => 125,
            'endLine' => 125,
            'startTokenPos' => 169,
            'startFilePos' => 3430,
            'endTokenPos' => 169,
            'endFilePos' => 3439,
          ),
        ),
        'docComment' => '/**
 * The default paper orientation.
 *
 * The orientation of the page (portrait or landscape).
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 125,
        'endLine' => 125,
        'startColumn' => 5,
        'endColumn' => 50,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'defaultFont' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'defaultFont',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '"serif"',
          'attributes' => 
          array (
            'startLine' => 134,
            'endLine' => 134,
            'startTokenPos' => 180,
            'startFilePos' => 3633,
            'endTokenPos' => 180,
            'endFilePos' => 3639,
          ),
        ),
        'docComment' => '/**
 * The default font family
 *
 * Used if no suitable fonts can be found. This must exist in the font folder.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 134,
        'endLine' => 134,
        'startColumn' => 5,
        'endColumn' => 35,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'dpi' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'dpi',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '96',
          'attributes' => 
          array (
            'startLine' => 157,
            'endLine' => 157,
            'startTokenPos' => 191,
            'startFilePos' => 4594,
            'endTokenPos' => 191,
            'endFilePos' => 4595,
          ),
        ),
        'docComment' => '/**
 * Image DPI setting
 *
 * This setting determines the default DPI setting for images and fonts.  The
 * DPI may be overridden for inline images by explicitly setting the
 * image\'s width & height style attributes (i.e. if the image\'s native
 * width is 600 pixels and you specify the image\'s width as 72 points,
 * the image will have a DPI of 600 in the rendered PDF.  The DPI of
 * background images can not be overridden and is controlled entirely
 * via this parameter.
 *
 * For the purposes of DOMPDF, pixels per inch (PPI) = dots per inch (DPI).
 * If a size in html is given as px (or without unit as image size),
 * this tells the corresponding size in pt at 72 DPI.
 * This adjusts the relative sizes to be similar to the rendering of the
 * html page in a reference browser.
 *
 * In pdf, always 1 pt = 1/72 inch
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 157,
        'endLine' => 157,
        'startColumn' => 5,
        'endColumn' => 22,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'fontHeightRatio' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'fontHeightRatio',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '1.1',
          'attributes' => 
          array (
            'startLine' => 164,
            'endLine' => 164,
            'startTokenPos' => 202,
            'startFilePos' => 4752,
            'endTokenPos' => 202,
            'endFilePos' => 4754,
          ),
        ),
        'docComment' => '/**
 * A ratio applied to the fonts height to be more like browsers\' line height
 *
 * @var float
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 164,
        'endLine' => 164,
        'startColumn' => 5,
        'endColumn' => 35,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'imageByteSizeLimit' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'imageByteSizeLimit',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '-1',
          'attributes' => 
          array (
            'startLine' => 171,
            'endLine' => 171,
            'startTokenPos' => 213,
            'startFilePos' => 4921,
            'endTokenPos' => 214,
            'endFilePos' => 4922,
          ),
        ),
        'docComment' => '/**
 * The maximum estimated in-memory size of an image allowed to be rendered, in bytes.
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 171,
        'endLine' => 171,
        'startColumn' => 5,
        'endColumn' => 37,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'isPhpEnabled' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'isPhpEnabled',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 192,
            'endLine' => 192,
            'startTokenPos' => 225,
            'startFilePos' => 5772,
            'endTokenPos' => 225,
            'endFilePos' => 5776,
          ),
        ),
        'docComment' => '/**
 * Enable embedded PHP
 *
 * If this setting is set to true then DOMPDF will automatically evaluate
 * embedded PHP contained within <script type="text/php"> ... </script> tags.
 *
 * ==== IMPORTANT ====
 * Enabling this for documents you do not trust (e.g. arbitrary remote html
 * pages) is a security risk. Embedded scripts are run with the same level of
 * system access available to dompdf. Set this option to false (recommended)
 * if you wish to process untrusted documents.
 *
 * This setting may increase the risk of system exploit. Do not change
 * this settings without understanding the consequences. Additional
 * documentation is available on the dompdf wiki at:
 * https://github.com/dompdf/dompdf/wiki
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 192,
        'endLine' => 192,
        'startColumn' => 5,
        'endColumn' => 34,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'isRemoteEnabled' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'isRemoteEnabled',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 214,
            'endLine' => 214,
            'startTokenPos' => 236,
            'startFilePos' => 6753,
            'endTokenPos' => 236,
            'endFilePos' => 6757,
          ),
        ),
        'docComment' => '/**
 * Enable remote file access
 *
 * If this setting is set to true, DOMPDF will access remote sites for
 * images and CSS files as required.
 *
 * ==== IMPORTANT ====
 * This can be a security risk, in particular in combination with isPhpEnabled and
 * allowing remote html code to be passed to $dompdf = new DOMPDF(); $dompdf->load_html(...);
 * This allows anonymous users to download legally doubtful internet content which on
 * tracing back appears to being downloaded by your server, or allows malicious php code
 * in remote html pages to be executed by your server with your account privileges.
 *
 * This setting may increase the risk of system exploit. Do not change
 * this settings without understanding the consequences. Additional
 * documentation is available on the dompdf wiki at:
 * https://github.com/dompdf/dompdf/wiki
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 214,
        'endLine' => 214,
        'startColumn' => 5,
        'endColumn' => 37,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'allowedRemoteHosts' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'allowedRemoteHosts',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 228,
            'endLine' => 228,
            'startTokenPos' => 247,
            'startFilePos' => 7171,
            'endTokenPos' => 247,
            'endFilePos' => 7174,
          ),
        ),
        'docComment' => '/**
 * List of allowed remote hosts
 *
 * Each value of the array must be a valid hostname.
 *
 * This will be used to filter which resources can be loaded in combination with
 * isRemoteEnabled. If isRemoteEnabled is FALSE, then this will have no effect.
 *
 * Leave to NULL to allow any remote host.
 *
 * @var array|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 228,
        'endLine' => 228,
        'startColumn' => 5,
        'endColumn' => 39,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'isPdfAEnabled' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'isPdfAEnabled',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 242,
            'endLine' => 242,
            'startTokenPos' => 258,
            'startFilePos' => 7648,
            'endTokenPos' => 258,
            'endFilePos' => 7652,
          ),
        ),
        'docComment' => '/**
 * Enable PDF/A-3 compliance mode
 *
 * ==== EXPERIMENTAL ====
 * This feature is currently only supported with the CPDF backend and will
 * have no effect if used with any other.
 *
 * Currently this mode only takes care of adding the necessary metadata, output intents, etc.
 * It does not enforce font embedding, it\'s up to you to embed the fonts you plan on using.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 242,
        'endLine' => 242,
        'startColumn' => 5,
        'endColumn' => 35,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'isJavascriptEnabled' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'isJavascriptEnabled',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'true',
          'attributes' => 
          array (
            'startLine' => 256,
            'endLine' => 256,
            'startTokenPos' => 269,
            'startFilePos' => 8108,
            'endTokenPos' => 269,
            'endFilePos' => 8111,
          ),
        ),
        'docComment' => '/**
 * Enable inline JavaScript
 *
 * If this setting is set to true then DOMPDF will automatically insert
 * JavaScript code contained within <script type="text/javascript"> ... </script>
 * tags as written into the PDF.
 *
 * NOTE: This is PDF-based JavaScript to be executed by the PDF viewer,
 * not browser-based JavaScript executed by Dompdf.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 256,
        'endLine' => 256,
        'startColumn' => 5,
        'endColumn' => 40,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'isHtml5ParserEnabled' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'isHtml5ParserEnabled',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'true',
          'attributes' => 
          array (
            'startLine' => 264,
            'endLine' => 264,
            'startTokenPos' => 280,
            'startFilePos' => 8242,
            'endTokenPos' => 280,
            'endFilePos' => 8245,
          ),
        ),
        'docComment' => '/**
 * Use the HTML5 Lib parser
 *
 * @deprecated
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 264,
        'endLine' => 264,
        'startColumn' => 5,
        'endColumn' => 41,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'isFontSubsettingEnabled' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'isFontSubsettingEnabled',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'true',
          'attributes' => 
          array (
            'startLine' => 271,
            'endLine' => 271,
            'startTokenPos' => 291,
            'startFilePos' => 8377,
            'endTokenPos' => 291,
            'endFilePos' => 8380,
          ),
        ),
        'docComment' => '/**
 * Whether to enable font subsetting or not.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 271,
        'endLine' => 271,
        'startColumn' => 5,
        'endColumn' => 44,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'debugPng' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'debugPng',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 276,
            'endLine' => 276,
            'startTokenPos' => 302,
            'startFilePos' => 8441,
            'endTokenPos' => 302,
            'endFilePos' => 8445,
          ),
        ),
        'docComment' => '/**
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 276,
        'endLine' => 276,
        'startColumn' => 5,
        'endColumn' => 30,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'debugKeepTemp' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'debugKeepTemp',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 281,
            'endLine' => 281,
            'startTokenPos' => 313,
            'startFilePos' => 8511,
            'endTokenPos' => 313,
            'endFilePos' => 8515,
          ),
        ),
        'docComment' => '/**
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 281,
        'endLine' => 281,
        'startColumn' => 5,
        'endColumn' => 35,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'debugCss' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'debugCss',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 286,
            'endLine' => 286,
            'startTokenPos' => 324,
            'startFilePos' => 8576,
            'endTokenPos' => 324,
            'endFilePos' => 8580,
          ),
        ),
        'docComment' => '/**
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 286,
        'endLine' => 286,
        'startColumn' => 5,
        'endColumn' => 30,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'debugLayout' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'debugLayout',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 291,
            'endLine' => 291,
            'startTokenPos' => 335,
            'startFilePos' => 8644,
            'endTokenPos' => 335,
            'endFilePos' => 8648,
          ),
        ),
        'docComment' => '/**
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 291,
        'endLine' => 291,
        'startColumn' => 5,
        'endColumn' => 33,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'debugLayoutLines' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'debugLayoutLines',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'true',
          'attributes' => 
          array (
            'startLine' => 296,
            'endLine' => 296,
            'startTokenPos' => 346,
            'startFilePos' => 8717,
            'endTokenPos' => 346,
            'endFilePos' => 8720,
          ),
        ),
        'docComment' => '/**
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 296,
        'endLine' => 296,
        'startColumn' => 5,
        'endColumn' => 37,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'debugLayoutBlocks' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'debugLayoutBlocks',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'true',
          'attributes' => 
          array (
            'startLine' => 301,
            'endLine' => 301,
            'startTokenPos' => 357,
            'startFilePos' => 8790,
            'endTokenPos' => 357,
            'endFilePos' => 8793,
          ),
        ),
        'docComment' => '/**
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 301,
        'endLine' => 301,
        'startColumn' => 5,
        'endColumn' => 38,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'debugLayoutInline' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'debugLayoutInline',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'true',
          'attributes' => 
          array (
            'startLine' => 306,
            'endLine' => 306,
            'startTokenPos' => 368,
            'startFilePos' => 8863,
            'endTokenPos' => 368,
            'endFilePos' => 8866,
          ),
        ),
        'docComment' => '/**
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 306,
        'endLine' => 306,
        'startColumn' => 5,
        'endColumn' => 38,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'debugLayoutPaddingBox' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'debugLayoutPaddingBox',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'true',
          'attributes' => 
          array (
            'startLine' => 311,
            'endLine' => 311,
            'startTokenPos' => 379,
            'startFilePos' => 8940,
            'endTokenPos' => 379,
            'endFilePos' => 8943,
          ),
        ),
        'docComment' => '/**
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 311,
        'endLine' => 311,
        'startColumn' => 5,
        'endColumn' => 42,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'pdfBackend' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'pdfBackend',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '"CPDF"',
          'attributes' => 
          array (
            'startLine' => 324,
            'endLine' => 324,
            'startTokenPos' => 390,
            'startFilePos' => 9385,
            'endTokenPos' => 390,
            'endFilePos' => 9390,
          ),
        ),
        'docComment' => '/**
 * The PDF rendering backend to use
 *
 * Valid settings are \'PDFLib\', \'CPDF\', \'GD\', and \'auto\'. \'auto\' will
 * look for PDFLib and use it if found, or if not it will fall back on
 * CPDF. \'GD\' renders PDFs to graphic files. {@link Dompdf\\CanvasFactory}
 * ultimately determines which rendering class to instantiate
 * based on this setting.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 324,
        'endLine' => 324,
        'startColumn' => 5,
        'endColumn' => 33,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'pdflibLicense' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'pdflibLicense',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '""',
          'attributes' => 
          array (
            'startLine' => 340,
            'endLine' => 340,
            'startTokenPos' => 401,
            'startFilePos' => 9883,
            'endTokenPos' => 401,
            'endFilePos' => 9884,
          ),
        ),
        'docComment' => '/**
 * PDFlib license key
 *
 * If you are using a licensed, commercial version of PDFlib, specify
 * your license key here.  If you are using PDFlib-Lite or are evaluating
 * the commercial version of PDFlib, comment out this setting.
 *
 * @link http://www.pdflib.com
 *
 * If pdflib present in web server and auto or selected explicitly above,
 * a real license code must exist!
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 340,
        'endLine' => 340,
        'startColumn' => 5,
        'endColumn' => 32,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'httpContext' => 
      array (
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'name' => 'httpContext',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * HTTP context created with stream_context_create()
 * Will be used for file_get_contents
 *
 * @link https://www.php.net/manual/context.php
 *
 * @var resource
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 350,
        'endLine' => 350,
        'startColumn' => 5,
        'endColumn' => 25,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
    ),
    'immediateMethods' => 
    array (
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
          'attributes' => 
          array (
            'name' => 'attributes',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 355,
                'endLine' => 355,
                'startTokenPos' => 426,
                'startFilePos' => 10218,
                'endTokenPos' => 426,
                'endFilePos' => 10221,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
              'data' => 
              array (
                'types' => 
                array (
                  0 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'array',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'null',
                      'isIdentifier' => true,
                    ),
                  ),
                ),
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 355,
            'endLine' => 355,
            'startColumn' => 33,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param array $attributes
 */',
        'startLine' => 355,
        'endLine' => 390,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'set' => 
      array (
        'name' => 'set',
        'parameters' => 
        array (
          'attributes' => 
          array (
            'name' => 'attributes',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 397,
            'endLine' => 397,
            'startColumn' => 25,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 397,
                'endLine' => 397,
                'startTokenPos' => 709,
                'startFilePos' => 11574,
                'endTokenPos' => 709,
                'endFilePos' => 11577,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 397,
            'endLine' => 397,
            'startColumn' => 38,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param array|string $attributes
 * @param null|mixed $value
 * @return $this
 */',
        'startLine' => 397,
        'endLine' => 427,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'get' => 
      array (
        'name' => 'get',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 433,
            'endLine' => 433,
            'startColumn' => 25,
            'endColumn' => 28,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param string $key
 * @return mixed
 */',
        'startLine' => 433,
        'endLine' => 457,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setPdfBackend' => 
      array (
        'name' => 'setPdfBackend',
        'parameters' => 
        array (
          'pdfBackend' => 
          array (
            'name' => 'pdfBackend',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 463,
            'endLine' => 463,
            'startColumn' => 35,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param string $pdfBackend
 * @return $this
 */',
        'startLine' => 463,
        'endLine' => 467,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getPdfBackend' => 
      array (
        'name' => 'getPdfBackend',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return string
 */',
        'startLine' => 472,
        'endLine' => 475,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setPdflibLicense' => 
      array (
        'name' => 'setPdflibLicense',
        'parameters' => 
        array (
          'pdflibLicense' => 
          array (
            'name' => 'pdflibLicense',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 481,
            'endLine' => 481,
            'startColumn' => 38,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param string $pdflibLicense
 * @return $this
 */',
        'startLine' => 481,
        'endLine' => 485,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getPdflibLicense' => 
      array (
        'name' => 'getPdflibLicense',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return string
 */',
        'startLine' => 490,
        'endLine' => 493,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setChroot' => 
      array (
        'name' => 'setChroot',
        'parameters' => 
        array (
          'chroot' => 
          array (
            'name' => 'chroot',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 499,
            'endLine' => 499,
            'startColumn' => 31,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'delimiter' => 
          array (
            'name' => 'delimiter',
            'default' => 
            array (
              'code' => '\',\'',
              'attributes' => 
              array (
                'startLine' => 499,
                'endLine' => 499,
                'startTokenPos' => 1296,
                'startFilePos' => 14632,
                'endTokenPos' => 1296,
                'endFilePos' => 14634,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 499,
            'endLine' => 499,
            'startColumn' => 40,
            'endColumn' => 55,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param array|string $chroot
 * @return $this
 */',
        'startLine' => 499,
        'endLine' => 507,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getAllowedProtocols' => 
      array (
        'name' => 'getAllowedProtocols',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return array
 */',
        'startLine' => 512,
        'endLine' => 515,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setAllowedProtocols' => 
      array (
        'name' => 'setAllowedProtocols',
        'parameters' => 
        array (
          'allowedProtocols' => 
          array (
            'name' => 'allowedProtocols',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 524,
            'endLine' => 524,
            'startColumn' => 41,
            'endColumn' => 63,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param array $allowedProtocols The protocols to allow, as an array
 * formatted as ["protocol://" => ["rules" => [callable]], ...]
 * or ["protocol://", ...]
 *
 * @return $this
 */',
        'startLine' => 524,
        'endLine' => 542,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'addAllowedProtocol' => 
      array (
        'name' => 'addAllowedProtocol',
        'parameters' => 
        array (
          'protocol' => 
          array (
            'name' => 'protocol',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 551,
            'endLine' => 551,
            'startColumn' => 40,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'rules' => 
          array (
            'name' => 'rules',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'callable',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => true,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 551,
            'endLine' => 551,
            'startColumn' => 58,
            'endColumn' => 75,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Adds a new protocol to the allowed protocols collection
 *
 * @param string $protocol The scheme to add (e.g. "http://")
 * @param callable $rule A callable that validates the protocol
 * @return $this
 */',
        'startLine' => 551,
        'endLine' => 573,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getArtifactPathValidation' => 
      array (
        'name' => 'getArtifactPathValidation',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return array
 */',
        'startLine' => 578,
        'endLine' => 581,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setArtifactPathValidation' => 
      array (
        'name' => 'setArtifactPathValidation',
        'parameters' => 
        array (
          'validator' => 
          array (
            'name' => 'validator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 587,
            'endLine' => 587,
            'startColumn' => 47,
            'endColumn' => 56,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param callable $validator
 * @return $this
 */',
        'startLine' => 587,
        'endLine' => 591,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getChroot' => 
      array (
        'name' => 'getChroot',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return array
 */',
        'startLine' => 596,
        'endLine' => 603,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setDebugCss' => 
      array (
        'name' => 'setDebugCss',
        'parameters' => 
        array (
          'debugCss' => 
          array (
            'name' => 'debugCss',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 609,
            'endLine' => 609,
            'startColumn' => 33,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param boolean $debugCss
 * @return $this
 */',
        'startLine' => 609,
        'endLine' => 613,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getDebugCss' => 
      array (
        'name' => 'getDebugCss',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return boolean
 */',
        'startLine' => 618,
        'endLine' => 621,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setDebugKeepTemp' => 
      array (
        'name' => 'setDebugKeepTemp',
        'parameters' => 
        array (
          'debugKeepTemp' => 
          array (
            'name' => 'debugKeepTemp',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 627,
            'endLine' => 627,
            'startColumn' => 38,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param boolean $debugKeepTemp
 * @return $this
 */',
        'startLine' => 627,
        'endLine' => 631,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getDebugKeepTemp' => 
      array (
        'name' => 'getDebugKeepTemp',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return boolean
 */',
        'startLine' => 636,
        'endLine' => 639,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setDebugLayout' => 
      array (
        'name' => 'setDebugLayout',
        'parameters' => 
        array (
          'debugLayout' => 
          array (
            'name' => 'debugLayout',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 645,
            'endLine' => 645,
            'startColumn' => 36,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param boolean $debugLayout
 * @return $this
 */',
        'startLine' => 645,
        'endLine' => 649,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getDebugLayout' => 
      array (
        'name' => 'getDebugLayout',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return boolean
 */',
        'startLine' => 654,
        'endLine' => 657,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setDebugLayoutBlocks' => 
      array (
        'name' => 'setDebugLayoutBlocks',
        'parameters' => 
        array (
          'debugLayoutBlocks' => 
          array (
            'name' => 'debugLayoutBlocks',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 663,
            'endLine' => 663,
            'startColumn' => 42,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param boolean $debugLayoutBlocks
 * @return $this
 */',
        'startLine' => 663,
        'endLine' => 667,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getDebugLayoutBlocks' => 
      array (
        'name' => 'getDebugLayoutBlocks',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return boolean
 */',
        'startLine' => 672,
        'endLine' => 675,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setDebugLayoutInline' => 
      array (
        'name' => 'setDebugLayoutInline',
        'parameters' => 
        array (
          'debugLayoutInline' => 
          array (
            'name' => 'debugLayoutInline',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 681,
            'endLine' => 681,
            'startColumn' => 42,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param boolean $debugLayoutInline
 * @return $this
 */',
        'startLine' => 681,
        'endLine' => 685,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getDebugLayoutInline' => 
      array (
        'name' => 'getDebugLayoutInline',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return boolean
 */',
        'startLine' => 690,
        'endLine' => 693,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setDebugLayoutLines' => 
      array (
        'name' => 'setDebugLayoutLines',
        'parameters' => 
        array (
          'debugLayoutLines' => 
          array (
            'name' => 'debugLayoutLines',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 699,
            'endLine' => 699,
            'startColumn' => 41,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param boolean $debugLayoutLines
 * @return $this
 */',
        'startLine' => 699,
        'endLine' => 703,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getDebugLayoutLines' => 
      array (
        'name' => 'getDebugLayoutLines',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return boolean
 */',
        'startLine' => 708,
        'endLine' => 711,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setDebugLayoutPaddingBox' => 
      array (
        'name' => 'setDebugLayoutPaddingBox',
        'parameters' => 
        array (
          'debugLayoutPaddingBox' => 
          array (
            'name' => 'debugLayoutPaddingBox',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 717,
            'endLine' => 717,
            'startColumn' => 46,
            'endColumn' => 67,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param boolean $debugLayoutPaddingBox
 * @return $this
 */',
        'startLine' => 717,
        'endLine' => 721,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getDebugLayoutPaddingBox' => 
      array (
        'name' => 'getDebugLayoutPaddingBox',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return boolean
 */',
        'startLine' => 726,
        'endLine' => 729,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setDebugPng' => 
      array (
        'name' => 'setDebugPng',
        'parameters' => 
        array (
          'debugPng' => 
          array (
            'name' => 'debugPng',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 735,
            'endLine' => 735,
            'startColumn' => 33,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param boolean $debugPng
 * @return $this
 */',
        'startLine' => 735,
        'endLine' => 739,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getDebugPng' => 
      array (
        'name' => 'getDebugPng',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return boolean
 */',
        'startLine' => 744,
        'endLine' => 747,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setDefaultFont' => 
      array (
        'name' => 'setDefaultFont',
        'parameters' => 
        array (
          'defaultFont' => 
          array (
            'name' => 'defaultFont',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 753,
            'endLine' => 753,
            'startColumn' => 36,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param string $defaultFont
 * @return $this
 */',
        'startLine' => 753,
        'endLine' => 761,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getDefaultFont' => 
      array (
        'name' => 'getDefaultFont',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return string
 */',
        'startLine' => 766,
        'endLine' => 769,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setDefaultMediaType' => 
      array (
        'name' => 'setDefaultMediaType',
        'parameters' => 
        array (
          'defaultMediaType' => 
          array (
            'name' => 'defaultMediaType',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 775,
            'endLine' => 775,
            'startColumn' => 41,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param string $defaultMediaType
 * @return $this
 */',
        'startLine' => 775,
        'endLine' => 779,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getDefaultMediaType' => 
      array (
        'name' => 'getDefaultMediaType',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return string
 */',
        'startLine' => 784,
        'endLine' => 787,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setDefaultPaperSize' => 
      array (
        'name' => 'setDefaultPaperSize',
        'parameters' => 
        array (
          'defaultPaperSize' => 
          array (
            'name' => 'defaultPaperSize',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 793,
            'endLine' => 793,
            'startColumn' => 41,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param string|float[] $defaultPaperSize
 * @return $this
 */',
        'startLine' => 793,
        'endLine' => 797,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setDefaultPaperOrientation' => 
      array (
        'name' => 'setDefaultPaperOrientation',
        'parameters' => 
        array (
          'defaultPaperOrientation' => 
          array (
            'name' => 'defaultPaperOrientation',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 803,
            'endLine' => 803,
            'startColumn' => 48,
            'endColumn' => 78,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param string $defaultPaperOrientation
 * @return $this
 */',
        'startLine' => 803,
        'endLine' => 807,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getDefaultPaperSize' => 
      array (
        'name' => 'getDefaultPaperSize',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return string|float[]
 */',
        'startLine' => 812,
        'endLine' => 815,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getDefaultPaperOrientation' => 
      array (
        'name' => 'getDefaultPaperOrientation',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return string
 */',
        'startLine' => 820,
        'endLine' => 823,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setDpi' => 
      array (
        'name' => 'setDpi',
        'parameters' => 
        array (
          'dpi' => 
          array (
            'name' => 'dpi',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 829,
            'endLine' => 829,
            'startColumn' => 28,
            'endColumn' => 31,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param int $dpi
 * @return $this
 */',
        'startLine' => 829,
        'endLine' => 833,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getDpi' => 
      array (
        'name' => 'getDpi',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return int
 */',
        'startLine' => 838,
        'endLine' => 841,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setFontCache' => 
      array (
        'name' => 'setFontCache',
        'parameters' => 
        array (
          'fontCache' => 
          array (
            'name' => 'fontCache',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 847,
            'endLine' => 847,
            'startColumn' => 34,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param string $fontCache
 * @return $this
 */',
        'startLine' => 847,
        'endLine' => 853,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getFontCache' => 
      array (
        'name' => 'getFontCache',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return string
 */',
        'startLine' => 858,
        'endLine' => 861,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setFontDir' => 
      array (
        'name' => 'setFontDir',
        'parameters' => 
        array (
          'fontDir' => 
          array (
            'name' => 'fontDir',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 867,
            'endLine' => 867,
            'startColumn' => 32,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param string $fontDir
 * @return $this
 */',
        'startLine' => 867,
        'endLine' => 873,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getFontDir' => 
      array (
        'name' => 'getFontDir',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return string
 */',
        'startLine' => 878,
        'endLine' => 881,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setFontHeightRatio' => 
      array (
        'name' => 'setFontHeightRatio',
        'parameters' => 
        array (
          'fontHeightRatio' => 
          array (
            'name' => 'fontHeightRatio',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 887,
            'endLine' => 887,
            'startColumn' => 40,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param float $fontHeightRatio
 * @return $this
 */',
        'startLine' => 887,
        'endLine' => 891,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getFontHeightRatio' => 
      array (
        'name' => 'getFontHeightRatio',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return float
 */',
        'startLine' => 896,
        'endLine' => 899,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setImageByteSizeLimit' => 
      array (
        'name' => 'setImageByteSizeLimit',
        'parameters' => 
        array (
          'imageByteSizeLimit' => 
          array (
            'name' => 'imageByteSizeLimit',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 905,
            'endLine' => 905,
            'startColumn' => 43,
            'endColumn' => 61,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param int $imageMemoryLimit
 * @return $this
 */',
        'startLine' => 905,
        'endLine' => 921,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getImageByteSizeLimit' => 
      array (
        'name' => 'getImageByteSizeLimit',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return int
 */',
        'startLine' => 926,
        'endLine' => 929,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setIsFontSubsettingEnabled' => 
      array (
        'name' => 'setIsFontSubsettingEnabled',
        'parameters' => 
        array (
          'isFontSubsettingEnabled' => 
          array (
            'name' => 'isFontSubsettingEnabled',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 935,
            'endLine' => 935,
            'startColumn' => 48,
            'endColumn' => 71,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param boolean $isFontSubsettingEnabled
 * @return $this
 */',
        'startLine' => 935,
        'endLine' => 939,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getIsFontSubsettingEnabled' => 
      array (
        'name' => 'getIsFontSubsettingEnabled',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return boolean
 */',
        'startLine' => 944,
        'endLine' => 947,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'isFontSubsettingEnabled' => 
      array (
        'name' => 'isFontSubsettingEnabled',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return boolean
 */',
        'startLine' => 952,
        'endLine' => 955,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setIsHtml5ParserEnabled' => 
      array (
        'name' => 'setIsHtml5ParserEnabled',
        'parameters' => 
        array (
          'isHtml5ParserEnabled' => 
          array (
            'name' => 'isHtml5ParserEnabled',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 962,
            'endLine' => 962,
            'startColumn' => 45,
            'endColumn' => 65,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @deprecated
 * @param boolean $isHtml5ParserEnabled
 * @return $this
 */',
        'startLine' => 962,
        'endLine' => 966,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getIsHtml5ParserEnabled' => 
      array (
        'name' => 'getIsHtml5ParserEnabled',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @deprecated
 * @return boolean
 */',
        'startLine' => 972,
        'endLine' => 975,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'isHtml5ParserEnabled' => 
      array (
        'name' => 'isHtml5ParserEnabled',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @deprecated
 * @return boolean
 */',
        'startLine' => 981,
        'endLine' => 984,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setIsJavascriptEnabled' => 
      array (
        'name' => 'setIsJavascriptEnabled',
        'parameters' => 
        array (
          'isJavascriptEnabled' => 
          array (
            'name' => 'isJavascriptEnabled',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 990,
            'endLine' => 990,
            'startColumn' => 44,
            'endColumn' => 63,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param boolean $isJavascriptEnabled
 * @return $this
 */',
        'startLine' => 990,
        'endLine' => 994,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getIsJavascriptEnabled' => 
      array (
        'name' => 'getIsJavascriptEnabled',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return boolean
 */',
        'startLine' => 999,
        'endLine' => 1002,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'isJavascriptEnabled' => 
      array (
        'name' => 'isJavascriptEnabled',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return boolean
 */',
        'startLine' => 1007,
        'endLine' => 1010,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setIsPhpEnabled' => 
      array (
        'name' => 'setIsPhpEnabled',
        'parameters' => 
        array (
          'isPhpEnabled' => 
          array (
            'name' => 'isPhpEnabled',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1016,
            'endLine' => 1016,
            'startColumn' => 37,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param boolean $isPhpEnabled
 * @return $this
 */',
        'startLine' => 1016,
        'endLine' => 1020,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getIsPhpEnabled' => 
      array (
        'name' => 'getIsPhpEnabled',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return boolean
 */',
        'startLine' => 1025,
        'endLine' => 1028,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'isPhpEnabled' => 
      array (
        'name' => 'isPhpEnabled',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return boolean
 */',
        'startLine' => 1033,
        'endLine' => 1036,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setIsRemoteEnabled' => 
      array (
        'name' => 'setIsRemoteEnabled',
        'parameters' => 
        array (
          'isRemoteEnabled' => 
          array (
            'name' => 'isRemoteEnabled',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1042,
            'endLine' => 1042,
            'startColumn' => 40,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param boolean $isRemoteEnabled
 * @return $this
 */',
        'startLine' => 1042,
        'endLine' => 1046,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getIsRemoteEnabled' => 
      array (
        'name' => 'getIsRemoteEnabled',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return boolean
 */',
        'startLine' => 1051,
        'endLine' => 1054,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'isRemoteEnabled' => 
      array (
        'name' => 'isRemoteEnabled',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return boolean
 */',
        'startLine' => 1059,
        'endLine' => 1062,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setAllowedRemoteHosts' => 
      array (
        'name' => 'setAllowedRemoteHosts',
        'parameters' => 
        array (
          'allowedRemoteHosts' => 
          array (
            'name' => 'allowedRemoteHosts',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1068,
            'endLine' => 1068,
            'startColumn' => 43,
            'endColumn' => 61,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param array|null $allowedRemoteHosts
 * @return $this
 */',
        'startLine' => 1068,
        'endLine' => 1081,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getAllowedRemoteHosts' => 
      array (
        'name' => 'getAllowedRemoteHosts',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return array|null
 */',
        'startLine' => 1086,
        'endLine' => 1089,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setIsPdfAEnabled' => 
      array (
        'name' => 'setIsPdfAEnabled',
        'parameters' => 
        array (
          'isPdfAEnabled' => 
          array (
            'name' => 'isPdfAEnabled',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1095,
            'endLine' => 1095,
            'startColumn' => 38,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param boolean $isRemoteEnabled
 * @return $this
 */',
        'startLine' => 1095,
        'endLine' => 1099,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getIsPdfAEnabled' => 
      array (
        'name' => 'getIsPdfAEnabled',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return boolean
 */',
        'startLine' => 1104,
        'endLine' => 1107,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'isPdfAEnabled' => 
      array (
        'name' => 'isPdfAEnabled',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return boolean
 */',
        'startLine' => 1112,
        'endLine' => 1115,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setLogOutputFile' => 
      array (
        'name' => 'setLogOutputFile',
        'parameters' => 
        array (
          'logOutputFile' => 
          array (
            'name' => 'logOutputFile',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1121,
            'endLine' => 1121,
            'startColumn' => 38,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param string $logOutputFile
 * @return $this
 */',
        'startLine' => 1121,
        'endLine' => 1127,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getLogOutputFile' => 
      array (
        'name' => 'getLogOutputFile',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return string
 */',
        'startLine' => 1132,
        'endLine' => 1135,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setTempDir' => 
      array (
        'name' => 'setTempDir',
        'parameters' => 
        array (
          'tempDir' => 
          array (
            'name' => 'tempDir',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1141,
            'endLine' => 1141,
            'startColumn' => 32,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param string $tempDir
 * @return $this
 */',
        'startLine' => 1141,
        'endLine' => 1147,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getTempDir' => 
      array (
        'name' => 'getTempDir',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return string
 */',
        'startLine' => 1152,
        'endLine' => 1155,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setRootDir' => 
      array (
        'name' => 'setRootDir',
        'parameters' => 
        array (
          'rootDir' => 
          array (
            'name' => 'rootDir',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1161,
            'endLine' => 1161,
            'startColumn' => 32,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param string $rootDir
 * @return $this
 */',
        'startLine' => 1161,
        'endLine' => 1167,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getRootDir' => 
      array (
        'name' => 'getRootDir',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return string
 */',
        'startLine' => 1172,
        'endLine' => 1175,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'setHttpContext' => 
      array (
        'name' => 'setHttpContext',
        'parameters' => 
        array (
          'httpContext' => 
          array (
            'name' => 'httpContext',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1183,
            'endLine' => 1183,
            'startColumn' => 36,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Sets the HTTP context
 *
 * @param resource|array $httpContext
 * @return $this
 */',
        'startLine' => 1183,
        'endLine' => 1187,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'getHttpContext' => 
      array (
        'name' => 'getHttpContext',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the HTTP context
 *
 * @return resource
 */',
        'startLine' => 1194,
        'endLine' => 1197,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'validateArtifactPath' => 
      array (
        'name' => 'validateArtifactPath',
        'parameters' => 
        array (
          'path' => 
          array (
            'name' => 'path',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
              'data' => 
              array (
                'types' => 
                array (
                  0 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'string',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'null',
                      'isIdentifier' => true,
                    ),
                  ),
                ),
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1200,
            'endLine' => 1200,
            'startColumn' => 42,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'option' => 
          array (
            'name' => 'option',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1200,
            'endLine' => 1200,
            'startColumn' => 57,
            'endColumn' => 70,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 1200,
        'endLine' => 1210,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'validateLocalUri' => 
      array (
        'name' => 'validateLocalUri',
        'parameters' => 
        array (
          'uri' => 
          array (
            'name' => 'uri',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1212,
            'endLine' => 1212,
            'startColumn' => 38,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 1212,
        'endLine' => 1243,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'validatePharUri' => 
      array (
        'name' => 'validatePharUri',
        'parameters' => 
        array (
          'uri' => 
          array (
            'name' => 'uri',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1245,
            'endLine' => 1245,
            'startColumn' => 37,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 1245,
        'endLine' => 1253,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
      'validateRemoteUri' => 
      array (
        'name' => 'validateRemoteUri',
        'parameters' => 
        array (
          'uri' => 
          array (
            'name' => 'uri',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1255,
            'endLine' => 1255,
            'startColumn' => 39,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 1255,
        'endLine' => 1275,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Options',
        'implementingClassName' => 'Dompdf\\Options',
        'currentClassName' => 'Dompdf\\Options',
        'aliasName' => NULL,
      ),
    ),
    'traitsData' => 
    array (
      'aliases' => 
      array (
      ),
      'modifiers' => 
      array (
      ),
      'precedences' => 
      array (
      ),
      'hashes' => 
      array (
      ),
    ),
  ),
));