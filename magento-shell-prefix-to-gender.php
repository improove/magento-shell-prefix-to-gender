<?php

/**
 * Set gender by reading the prefix of the user
 *
 * PHP version 5
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category  Improove
 * @package   Improove
 * @author    Improove <robert@improove.se>
 * @copyright 2011 Improove
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @version   SVN: $Id$
 * @link      http://improove.se
 */

/**
 * Make sure we're on the right version
 */
if (version_compare(PHP_VERSION, '5.2.17', '>')) {
    die("PHP version should not be greater than 5.2. Your version: " .
        PHP_VERSION . "\n");
}

require_once 'abstract.php';

/**
 * Improove Prefix Converter script
 * 
 * This script will set the gender on users depending on the prefix
 * 
 * @category Improove
 * @package  Mage_Shell
 * @author   Improove <robert@improove.se>
 * @license  http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link     http://improove.se
 */
class Improove_Prefix_Converter extends Mage_Shell_Abstract
{
    
    protected $male = false;
    protected $female = false;
    
    /**
     * Initiate the script and set required variables
     * 
     * @return boolean
     */
    protected function init()
    {
        // Fetch the attributes gender and children
        $eavConfig = Mage::getSingleton('eav/config');
        $attribute = $eavConfig->getAttribute('customer', 'gender');
        if ($attribute->usesSource()) {
            $options = $attribute->getSource()->getAllOptions(false);
        }
        
        // Loop the results and parse them
        foreach ( $options as $data ) {
            if ( strtolower($data['label']) == 'male' ) {
                $this->male = $data['value'];
            }
            if ( strtolower($data['label']) == 'female' ) {
                $this->female = $data['value'];
            }
        }
        
        // Make sure both male and female ID's are set
        if ( !$this->female || !$this->male ) {
            die("Unable to get both gender id ");
        }
        
    }
    
    /**
     * Retrieve Usage Help Message
     *
     * @return string
     */
    public function usageHelp()
    {
        return <<<USAGE
Usage: php -f magento-shell-prefix-to-gender.php [options]
       php -f magento-shell-prefix-to-gender.php convert --gender male --prefix "MR"

  list                  Show all prefixes in system
  convert               Update all users gender
  --prefix <prefix>     The prefix to change
  --gender <gender>     The gender to set
  --force               Force the changes
  --verbose             Show more details
  help                  This help
  
  <prefix>    Use "list" to show all prefixes in system
  <gender>    Either male or female

USAGE;
    }
    
    /**
     * Run script
     * 
     * @return boolean
     */
    public function run()
    {
        if ( $this->getArg('list') ) {
            echo $this->listPrefixes();
        } else if ( $this->getArg('convert') ) {
            if ( !$this->getArg('gender') || !$this->getArg('prefix') ) {
                print "Missing --gender and --prefix arguments\n";
                $this->usageHelp();
            } else {
                $this->init();
                $force = $this->getArg('force');
                $verbose = $this->getArg('verbose');
                switch ( strtolower($this->getArg('gender')) ) {
                case 'male':
                case 'female':
                    $this->convert(
                        $this->getArg('prefix'),
                        $this->getArg('gender'),
                        $force,
                        $verbose
                    );
                    break;
                default:
                    print "Unknown gender \"" . $this->getArg('gender') . "\"\n";
                    print $this->usageHelp();
                    break;
                }
            }
            
        } else {
            echo $this->usageHelp();
        }
        return true;
    }
    
    /**
     * Perform the convert
     * 
     * @param string  $prefix  The prefix
     * @param string  $gender  The gender
     * @param boolean $force   Force the change
     * @param boolean $verbose Show extra output information
     * 
     * @return boolean
     */
    protected function convert($prefix, $gender,$force=false, $verbose=false)
    {
        
        print "Make sure these values are correct:\n";
        print "Female ID: " . $this->female . "\n";
        print "Male ID  : " . $this->male . "\n";
        
        // Make sure we really want to do this
        print "Ready to update users with \"" . $prefix . "\" prefix to " .
            $gender . " gender...\n";
        print "OK to proceed? [y/N] ";
        
        if ( strtolower($gender) == 'male' ) {
            $gender_id = $this->male;
        }
        if ( strtolower($gender) == 'female' ) {
            $gender_id = $this->female;
        }
        
        if ( !isset($gender_id) ) {
            die("Unable to set gender ID\n");
        }

        $keyPress = fgets(STDIN);
        if ( strtolower(trim($keyPress)) == 'y' ) {

            print "Updating users, please wait... ";
            if ( $verbose === true ) {
                print "\n";
            }
            
            // Get a list of all customers
            $customers = $this->getAllCustomers();
            $count = 0;
            foreach ( $customers as $customer ) {
                $c = $customer->toArray();
                if ( $c['prefix'] == $prefix ) {
                    if ( $verbose === true ) {
                        print "Customer #" . $c['entity_id'] . ", " .
                        $c['firstname'] . ' ' . $c['lastname'] . "... ";
                    }
                    if ( isset($c['gender']) && $c['gender'] !== '' && !$force ) {
                        if ( $verbose ) {
                            print "Gender is already set!\n";
                        }
                    } else {
                        $this->updateGender($c['entity_id'], $gender_id);
                        if ( $result && $verbose ) {
                            print "Done!\n";
                        }
                        $count++;
                    }
                }
            }
            
            print "All done!\n";
            print "Number of users updated: " . strval($count) . "\n";
            print "We're all done!\n";
            
            
        } else {
            print "Aborted.\n";
        }
        
        return true;
    }
    
    /**
     * Update the gender for user
     * 
     * @param integer $entity_id The user id
     * @param integer $gender_id The gender id
     * 
     * @return boolean 
     */
    protected function updateGender($entity_id, $gender_id)
    {
        $customerObject = Mage::getModel('customer/customer')
            ->load($entity_id);
        $customerObject->setGender($gender_id);
        $customerObject->save();
        return true;
    }


    
    /**
     * List all prefixed used in system
     * 
     * @return string String with the prefixes and 
     */
    public function listPrefixes()
    {
        // Setup an empty array for the prefixes
        $prefixes = array();
        
        // Fetch all customers
        $customers = $this->getAllCustomers();
        
        // Loop through all customers to fetch the prefix
        foreach ( $customers as $customer ) {
            if ( !isset($prefixes[$customer->getPrefix()]) ) {
                $prefixes[$customer->getPrefix()] = 1;
            }
        }
        
        // Output the result
        $text = "Prefixes in system:\n";
        foreach ( $prefixes as $prefix => $temp ) {
            $text .= "  " . $prefix . "\n";
        }
        
        return $text;
    }
    
    /**
     * Get all customers from the system
     * 
     * @return object 
     */
    protected function getAllCustomers()
    {
        return Mage::getModel('customer/customer')
            ->getCollection()
            ->addAttributeToSelect('*');
    }
    
}

$shell = new Improove_Prefix_Converter();
$shell->run();