<?php if (! defined('BASEPATH')) exit('No direct script access');
/**
 * Switch between different databases for different languages
 *
 * @author      Christian Koller
 * @link        http://www.kollerat.com/
 * @package     PyroCMS
 * @subpackage  Libraries
 * @category    Libraries
 *
 */
class Langdatabase
{
    /**
     * Constructor method
     *
     * @access public
     * @return void
     */
    public function __construct()
    {
        // Get the CI instance
        $CI =& get_instance();
        
        //Load the database config
        $CI->config->load('database_langinfo');

        $database_default_language = $CI->config->item('database_default_language');

        //Get the database group name to use and set the current database language
        $db_to_use = ENV;
        if (CURRENT_LANGUAGE!==$database_default_language) {
            //Validate the language
            $database_groups = $CI->config->item('database_groups');

            //If the database group for the CURRENT_LANGUAGE exists, use it
            if (in_array($db_to_use.'_'.CURRENT_LANGUAGE, $database_groups)) {
                $db_to_use = $db_to_use.'_'.CURRENT_LANGUAGE;
            }

            define('CURRENT_DB_LANGUAGE', CURRENT_LANGUAGE);
        } else {
            define('CURRENT_DB_LANGUAGE', $database_default_language);
        }
        
        $CI->load->database($db_to_use);
    }

}
?>