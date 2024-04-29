<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file contains classes used to manage the navigation structures within Moodle.
 *
 * @since      Moodle 2.0
 * @package    core
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_contentbank\contentbank;
use core\navigation\views\primary;
use core\navigation\views\secondary;
use core\navigation\output\primary as primaryoutput;
use core\output\activity_header;

defined('MOODLE_INTERNAL') || die();
define('NAVIGATION_CACHE_NAME', 'navigation');
define('NAVIGATION_SITE_ADMIN_CACHE_NAME', 'navigationsiteadmin'); 

/**
 * Navigation node collection
 *
 * This class is responsible for managing a collection of navigation nodes.
 * It is required because a node's unique identifier is a combination of both its
 * key and its type.
 *
 * Originally an array was used with a string key that was a combination of the two
 * however it was decided that a better solution would be to use a class that
 * implements the standard IteratorAggregate interface.
 *
 * @package   core
 * @category  navigation
 * @copyright 2010 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class orca_navigation_node_collection implements IteratorAggregate, Countable {
    /**
     * A multidimensional array to where the first key is the type and the second
     * key is the nodes key.
     * @var array
     */
    protected $collection = array();
    /**
     * An array that contains references to nodes in the same order they were added.
     * This is maintained as a progressive array.
     * @var array
     */
    protected $orderedcollection = array();
    /**
     * A reference to the last node that was added to the collection
     * @var orca_navigation_node
     */
    protected $last = null;
    /**
     * The total number of items added to this array.
     * @var int
     */
    protected $count = 0;

    /**
     * Label for collection of nodes.
     * @var string
     */
    protected $collectionlabel = '';

    /**
     * Adds a navigation node to the collection
     *
     * @param orca_navigation_node $node Node to add
     * @param string $beforekey If specified, adds before a node with this key,
     *   otherwise adds at end
     * @return orca_navigation_node Added node
     */
    public function add(orca_navigation_node $node, $beforekey=null) {
        global $CFG;
        $key = $node->key;
        $type = $node->type;

        // First check we have a 2nd dimension for this type
        if (!array_key_exists($type, $this->orderedcollection)) {
            $this->orderedcollection[$type] = array();
        }
        // Check for a collision and report if debugging is turned on
        if ($CFG->debug && array_key_exists($key, $this->orderedcollection[$type])) {
            debugging('Navigation node intersect: Adding a node that already exists '.$key, DEBUG_DEVELOPER);
        }

        // Find the key to add before
        $newindex = $this->count;
        $last = true;
        if ($beforekey !== null) {
            foreach ($this->collection as $index => $othernode) {
                if ($othernode->key === $beforekey) {
                    $newindex = $index;
                    $last = false;
                    break;
                }
            }
            if ($newindex === $this->count) {
                debugging('Navigation node add_before: Reference node not found ' . $beforekey .
                        ', options: ' . implode(' ', $this->get_key_list()), DEBUG_DEVELOPER);
            }
        }

        // Add the node to the appropriate place in the by-type structure (which
        // is not ordered, despite the variable name)
        $this->orderedcollection[$type][$key] = $node;
        if (!$last) {
            // Update existing references in the ordered collection (which is the
            // one that isn't called 'ordered') to shuffle them along if required
            for ($oldindex = $this->count; $oldindex > $newindex; $oldindex--) {
                $this->collection[$oldindex] = $this->collection[$oldindex - 1];
            }
        }
        // Add a reference to the node to the progressive collection.
        $this->collection[$newindex] = $this->orderedcollection[$type][$key];
        // Update the last property to a reference to this new node.
        $this->last = $this->orderedcollection[$type][$key];

        // Reorder the array by index if needed
        if (!$last) {
            ksort($this->collection);
        }
        $this->count++;
        // Return the reference to the now added node
        return $node;
    }

    /**
     * Return a list of all the keys of all the nodes.
     * @return array the keys.
     */
    public function get_key_list() {
        $keys = array();
        foreach ($this->collection as $node) {
            $keys[] = $node->key;
        }
        return $keys;
    }

    /**
     * Set a label for this collection.
     *
     * @param string $label
     */
    public function set_collectionlabel($label) {
        $this->collectionlabel = $label;
    }

    /**
     * Return a label for this collection.
     *
     * @return string
     */
    public function get_collectionlabel() {
        return $this->collectionlabel;
    }

    /**
     * Fetches a node from this collection.
     *
     * @param string|int $key The key of the node we want to find.
     * @param int $type One of orca_navigation_node::TYPE_*.
     * @return orca_navigation_node|null
     */
    public function get($key, $type=null) {
        if ($type !== null) {
            // If the type is known then we can simply check and fetch
            if (!empty($this->orderedcollection[$type][$key])) {
                return $this->orderedcollection[$type][$key];
            }
        } else {
            // Because we don't know the type we look in the progressive array
            foreach ($this->collection as $node) {
                if ($node->key === $key) {
                    return $node;
                }
            }
        }
        return false;
    }

    /**
     * Searches for a node with matching key and type.
     *
     * This function searches both the nodes in this collection and all of
     * the nodes in each collection belonging to the nodes in this collection.
     *
     * Recursive.
     *
     * @param string|int $key  The key of the node we want to find.
     * @param int $type  One of orca_navigation_node::TYPE_*.
     * @return orca_navigation_node|null
     */
    public function find($key, $type=null) {
        if ($type !== null && array_key_exists($type, $this->orderedcollection) && array_key_exists($key, $this->orderedcollection[$type])) {
            return $this->orderedcollection[$type][$key];
        } else {
            $nodes = $this->getIterator();
            // Search immediate children first
            foreach ($nodes as &$node) {
                if ($node->key === $key && ($type === null || $type === $node->type)) {
                    return $node;
                }
            }
            // Now search each childs children
            foreach ($nodes as &$node) {
                $result = $node->children->find($key, $type);
                if ($result !== false) {
                    return $result;
                }
            }
        }
        return false;
    }

    /**
     * Fetches the last node that was added to this collection
     *
     * @return orca_navigation_node
     */
    public function last() {
        return $this->last;
    }

    /**
     * Fetches all nodes of a given type from this collection
     *
     * @param string|int $type  node type being searched for.
     * @return array ordered collection
     */
    public function type($type) {
        if (!array_key_exists($type, $this->orderedcollection)) {
            $this->orderedcollection[$type] = array();
        }
        return $this->orderedcollection[$type];
    }
    /**
     * Removes the node with the given key and type from the collection
     *
     * @param string|int $key The key of the node we want to find.
     * @param int $type
     * @return bool
     */
    public function remove($key, $type=null) {
        $child = $this->get($key, $type);
        if ($child !== false) {
            foreach ($this->collection as $colkey => $node) {
                if ($node->key === $key && (is_null($type) || $node->type == $type)) {
                    unset($this->collection[$colkey]);
                    $this->collection = array_values($this->collection);
                    break;
                }
            }
            unset($this->orderedcollection[$child->type][$child->key]);
            $this->count--;
            return true;
        }
        return false;
    }

    /**
     * Gets the number of nodes in this collection
     *
     * This option uses an internal count rather than counting the actual options to avoid
     * a performance hit through the count function.
     *
     * @return int
     */
    public function count(): int {
        return $this->count;
    }
    /**
     * Gets an array iterator for the collection.
     *
     * This is required by the IteratorAggregator interface and is used by routines
     * such as the foreach loop.
     *
     * @return ArrayIterator
     */
    public function getIterator(): Traversable {
        return new ArrayIterator($this->collection);
    }
}

/**
 * This class is used to represent a node in a navigation tree
 *
 * This class is used to represent a node in a navigation tree within Moodle,
 * the tree could be one of global navigation, settings navigation, or the navbar.
 * Each node can be one of two types either a Leaf (default) or a branch.
 * When a node is first created it is created as a leaf, when/if children are added
 * the node then becomes a branch.
 *
 * @package   core
 * @category  navigation
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class orca_navigation_node implements renderable {
    /** @var int Used to identify this node a leaf (default) 0 */
    const NODETYPE_LEAF =   0;
    /** @var int Used to identify this node a branch, happens with children  1 */
    const NODETYPE_BRANCH = 1;
    /** @var null Unknown node type null */
    const TYPE_UNKNOWN =    null;
    /** @var int System node type 0 */
    const TYPE_ROOTNODE =   0;
    /** @var int System node type 1 */
    const TYPE_SYSTEM =     1;
    /** @var int Category node type 10 */
    const TYPE_CATEGORY =   10;
    /** var int Category displayed in MyHome navigation node */
    const TYPE_MY_CATEGORY = 11;
    /** @var int Course node type 20 */
    const TYPE_COURSE =     20;
    /** @var int Course Structure node type 30 */
    const TYPE_SECTION =    30;
    /** @var int Activity node type, e.g. Forum, Quiz 40 */
    const TYPE_ACTIVITY =   40;
    /** @var int Resource node type, e.g. Link to a file, or label 50 */
    const TYPE_RESOURCE =   50;
    /** @var int A custom node type, default when adding without specifing type 60 */
    const TYPE_CUSTOM =     60;
    /** @var int Setting node type, used only within settings nav 70 */
    const TYPE_SETTING =    70;
    /** @var int site admin branch node type, used only within settings nav 71 */
    const TYPE_SITE_ADMIN = 71;
    /** @var int Setting node type, used only within settings nav 80 */
    const TYPE_USER =       80;
    /** @var int Setting node type, used for containers of no importance 90 */
    const TYPE_CONTAINER =  90;
    /** var int Course the current user is not enrolled in */
    const COURSE_OTHER = 0;
    /** var int Course the current user is enrolled in but not viewing */
    const COURSE_MY = 1;
    /** var int Course the current user is currently viewing */
    const COURSE_CURRENT = 2;
    /** var string The course index page navigation node */
    const COURSE_INDEX_PAGE = 'courseindexpage';

    /** @var int Parameter to aid the coder in tracking [optional] */
    public $id = null;
    /** @var string|int The identifier for the node, used to retrieve the node */
    public $key = null;
    /** @var string The text to use for the node */
    public $text = null;
    /** @var string Short text to use if requested [optional] */
    public $shorttext = null;
    /** @var string The title attribute for an action if one is defined */
    public $title = null;
    /** @var string A string that can be used to build a help button */
    public $helpbutton = null;
    /** @var moodle_url|action_link|null An action for the node (link) */
    public $action = null;
    /** @var pix_icon The path to an icon to use for this node */
    public $icon = null;
    /** @var int See TYPE_* constants defined for this class */
    public $type = self::TYPE_UNKNOWN;
    /** @var int See NODETYPE_* constants defined for this class */
    public $nodetype = self::NODETYPE_LEAF;
    /** @var bool If set to true the node will be collapsed by default */
    public $collapse = false;
    /** @var bool If set to true the node will be expanded by default */
    public $forceopen = false;
    /** @var array An array of CSS classes for the node */
    public $classes = array();
    /** @var orca_navigation_node_collection An array of child nodes */
    public $children = array();
    /** @var bool If set to true the node will be recognised as active */
    public $isactive = false;
    /** @var bool If set to true the node will be dimmed */
    public $hidden = false;
    /** @var bool If set to false the node will not be displayed */
    public $display = true;
    /** @var bool If set to true then an HR will be printed before the node */
    public $preceedwithhr = false;
    /** @var bool If set to true the the navigation bar should ignore this node */
    public $mainnavonly = false;
    /** @var bool If set to true a title will be added to the action no matter what */
    public $forcetitle = false;
    /** @var orca_navigation_node A reference to the node parent, you should never set this directly you should always call set_parent */
    public $parent = null;
    /** @var bool Override to not display the icon even if one is provided **/
    public $hideicon = false;
    /** @var bool Set to true if we KNOW that this node can be expanded.  */
    public $isexpandable = false;
    /** @var array */
    protected $namedtypes = array(0 => 'system', 10 => 'category', 20 => 'course', 30 => 'structure', 40 => 'activity',
                                  50 => 'resource', 60 => 'custom', 70 => 'setting', 71 => 'siteadmin', 80 => 'user',
                                  90 => 'container');
    /** @var moodle_url */
    protected static $fullmeurl = null;
    /** @var bool toogles auto matching of active node */
    public static $autofindactive = true;
    /** @var bool should we load full admin tree or rely on AJAX for performance reasons */
    protected static $loadadmintree = false;
    /** @var mixed If set to an int, that section will be included even if it has no activities */
    public $includesectionnum = false;
    /** @var bool does the node need to be loaded via ajax */
    public $requiresajaxloading = false;
    /** @var bool If set to true this node will be added to the "flat" navigation */
    public $showinflatnavigation = false;
    /** @var bool If set to true this node will be forced into a "more" menu whenever possible */
    public $forceintomoremenu = false;
    /** @var bool If set to true this node will be displayed in the "secondary" navigation when applicable */
    public $showinsecondarynavigation = true;
    /** @var bool If set to true the children of this node will be displayed within a submenu when applicable */
    public $showchildreninsubmenu = false;

    /**
     * Constructs a new orca_navigation_node
     *
     * @param array|string $properties Either an array of properties or a string to use
     *                     as the text for the node
     */
    public function __construct($properties) {
        if (is_array($properties)) {
            // Check the array for each property that we allow to set at construction.
            // text         - The main content for the node
            // shorttext    - A short text if required for the node
            // icon         - The icon to display for the node
            // type         - The type of the node
            // key          - The key to use to identify the node
            // parent       - A reference to the nodes parent
            // action       - The action to attribute to this node, usually a URL to link to
            if (array_key_exists('text', $properties)) {
                $this->text = $properties['text'];
            }
            if (array_key_exists('shorttext', $properties)) {
                $this->shorttext = $properties['shorttext'];
            }
            if (!array_key_exists('icon', $properties)) {
                $properties['icon'] = new pix_icon('i/navigationitem', '');
            }
            $this->icon = $properties['icon'];
            if ($this->icon instanceof pix_icon) {
                if (empty($this->icon->attributes['class'])) {
                    $this->icon->attributes['class'] = 'navicon';
                } else {
                    $this->icon->attributes['class'] .= ' navicon';
                }
            }
            if (array_key_exists('type', $properties)) {
                $this->type = $properties['type'];
            } else {
                $this->type = self::TYPE_CUSTOM;
            }
            if (array_key_exists('key', $properties)) {
                $this->key = $properties['key'];
            }
            // This needs to happen last because of the check_if_active call that occurs
            if (array_key_exists('action', $properties)) {
                $this->action = $properties['action'];
                if (is_string($this->action)) {
                    $this->action = new moodle_url($this->action);
                }
                if (self::$autofindactive) {
                    $this->check_if_active();
                }
            }
            if (array_key_exists('parent', $properties)) {
                $this->set_parent($properties['parent']);
            }
        } else if (is_string($properties)) {
            $this->text = $properties;
        }
        if ($this->text === null) {
            throw new coding_exception('You must set the text for the node when you create it.');
        }
        // Instantiate a new navigation node collection for this nodes children
        $this->children = new orca_navigation_node_collection();
    }

    /**
     * Checks if this node is the active node.
     *
     * This is determined by comparing the action for the node against the
     * defined URL for the page. A match will see this node marked as active.
     *
     * @param int $strength One of URL_MATCH_EXACT, URL_MATCH_PARAMS, or URL_MATCH_BASE
     * @return bool
     */
    public function check_if_active($strength=URL_MATCH_EXACT) {
        global $FULLME, $PAGE;
        // Set fullmeurl if it hasn't already been set
        if (self::$fullmeurl == null) {
            if ($PAGE->has_set_url()) {
                self::override_active_url(new moodle_url($PAGE->url));
            } else {
                self::override_active_url(new moodle_url($FULLME));
            }
        }

        // Compare the action of this node against the fullmeurl
        if ($this->action instanceof moodle_url && $this->action->compare(self::$fullmeurl, $strength)) {
            $this->make_active();
            return true;
        }
        return false;
    }

    /**
     * True if this nav node has siblings in the tree.
     *
     * @return bool
     */
    public function has_siblings() {
        if (empty($this->parent) || empty($this->parent->children)) {
            return false;
        }
        if ($this->parent->children instanceof orca_navigation_node_collection) {
            $count = $this->parent->children->count();
        } else {
            $count = count($this->parent->children);
        }
        return ($count > 1);
    }

    /**
     * Get a list of sibling navigation nodes at the same level as this one.
     *
     * @return bool|array of orca_navigation_node
     */
    public function get_siblings() {
        // Returns a list of the siblings of the current node for display in a flat navigation element. Either
        // the in-page links or the breadcrumb links.
        $siblings = false;

        if ($this->has_siblings()) {
            $siblings = [];
            foreach ($this->parent->children as $child) {
                if ($child->display) {
                    $siblings[] = $child;
                }
            }
        }
        return $siblings;
    }

    /**
     * This sets the URL that the URL of new nodes get compared to when locating
     * the active node.
     *
     * The active node is the node that matches the URL set here. By default this
     * is either $PAGE->url or if that hasn't been set $FULLME.
     *
     * @param moodle_url $url The url to use for the fullmeurl.
     * @param bool $loadadmintree use true if the URL point to administration tree
     */
    public static function override_active_url(moodle_url $url, $loadadmintree = false) {
        // Clone the URL, in case the calling script changes their URL later.
        self::$fullmeurl = new moodle_url($url);
        // True means we do not want AJAX loaded admin tree, required for all admin pages.
        if ($loadadmintree) {
            // Do not change back to false if already set.
            self::$loadadmintree = true;
        }
    }

    /**
     * Use when page is linked from the admin tree,
     * if not used navigation could not find the page using current URL
     * because the tree is not fully loaded.
     */
    public static function require_admin_tree() {
        self::$loadadmintree = true;
    }

    /**
     * Creates a navigation node, ready to add it as a child using add_node
     * function. (The created node needs to be added before you can use it.)
     * @param string $text
     * @param moodle_url|action_link $action
     * @param int $type
     * @param string $shorttext
     * @param string|int $key
     * @param pix_icon $icon
     * @return orca_navigation_node
     */
    public static function create($text, $action=null, $type=self::TYPE_CUSTOM,
            $shorttext=null, $key=null, pix_icon $icon=null) {
        if ($action && !($action instanceof moodle_url || $action instanceof action_link)) {
            debugging(
                "It is required that the action provided be either an action_url|moodle_url." .
                " Please update your definition.", E_NOTICE);
        }
        // Properties array used when creating the new navigation node
        $itemarray = array(
            'text' => $text,
            'type' => $type
        );
        // Set the action if one was provided
        if ($action!==null) {
            $itemarray['action'] = $action;
        }
        // Set the shorttext if one was provided
        if ($shorttext!==null) {
            $itemarray['shorttext'] = $shorttext;
        }
        // Set the icon if one was provided
        if ($icon!==null) {
            $itemarray['icon'] = $icon;
        }
        // Set the key
        $itemarray['key'] = $key;
        // Construct and return
        return new orca_navigation_node($itemarray);
    }

    /**
     * Adds a navigation node as a child of this node.
     *
     * @param string $text
     * @param moodle_url|action_link $action
     * @param int $type
     * @param string $shorttext
     * @param string|int $key
     * @param pix_icon $icon
     * @return orca_navigation_node
     */
    public function add($text, $action=null, $type=self::TYPE_CUSTOM, $shorttext=null, $key=null, pix_icon $icon=null) {
        if ($action && is_string($action)) {
            $action = new moodle_url($action);
        }
        // Create child node
        $childnode = self::create($text, $action, $type, $shorttext, $key, $icon);

        // Add the child to end and return
        return $this->add_node($childnode);
    }

    /**
     * Adds a navigation node as a child of this one, given a $node object
     * created using the create function.
     * @param orca_navigation_node $childnode Node to add
     * @param string $beforekey
     * @return orca_navigation_node The added node
     */
    public function add_node(orca_navigation_node $childnode, $beforekey=null) {
        // First convert the nodetype for this node to a branch as it will now have children
        if ($this->nodetype !== self::NODETYPE_BRANCH) {
            $this->nodetype = self::NODETYPE_BRANCH;
        }
        // Set the parent to this node
        $childnode->set_parent($this);

        // Default the key to the number of children if not provided
        if ($childnode->key === null) {
            $childnode->key = $this->children->count();
        }

        // Add the child using the orca_navigation_node_collections add method
        $node = $this->children->add($childnode, $beforekey);

        // If added node is a category node or the user is logged in and it's a course
        // then mark added node as a branch (makes it expandable by AJAX)
        $type = $childnode->type;
        if (($type == self::TYPE_CATEGORY) || (isloggedin() && ($type == self::TYPE_COURSE)) || ($type == self::TYPE_MY_CATEGORY) ||
                ($type === self::TYPE_SITE_ADMIN)) {
            $node->nodetype = self::NODETYPE_BRANCH;
        }
        // If this node is hidden mark it's children as hidden also
        if ($this->hidden) {
            $node->hidden = true;
        }
        // Return added node (reference returned by $this->children->add()
        return $node;
    }

    /**
     * Return a list of all the keys of all the child nodes.
     * @return array the keys.
     */
    public function get_children_key_list() {
        return $this->children->get_key_list();
    }

    /**
     * Searches for a node of the given type with the given key.
     *
     * This searches this node plus all of its children, and their children....
     * If you know the node you are looking for is a child of this node then please
     * use the get method instead.
     *
     * @param int|string $key The key of the node we are looking for
     * @param int $type One of orca_navigation_node::TYPE_*
     * @return orca_navigation_node|false
     */
    public function find($key, $type) {
        return $this->children->find($key, $type);
    }

    /**
     * Walk the tree building up a list of all the flat navigation nodes.
     *
     * @param orca_flat_navigation $nodes List of the found flat navigation nodes.
     * @param boolean $showdivider Show a divider before the first node.
     * @param string $label A label for the collection of navigation links.
     */
    public function build_flat_navigation_list(orca_flat_navigation $nodes, $showdivider = false, $label = '') {
        if ($this->showinflatnavigation) {
            $indent = 0;
            if ($this->type == self::TYPE_COURSE || $this->key === self::COURSE_INDEX_PAGE) {
                $indent = 1;
            }
            $flat = new orca_flat_navigation_node($this, $indent);
            $flat->set_showdivider($showdivider, $label);
            $nodes->add($flat);
        }
        foreach ($this->children as $child) {
            $child->build_flat_navigation_list($nodes, false);
        }
    }

    /**
     * Get the child of this node that has the given key + (optional) type.
     *
     * If you are looking for a node and want to search all children + their children
     * then please use the find method instead.
     *
     * @param int|string $key The key of the node we are looking for
     * @param int $type One of orca_navigation_node::TYPE_*
     * @return orca_navigation_node|false
     */
    public function get($key, $type=null) {
        return $this->children->get($key, $type);
    }

    /**
     * Removes this node.
     *
     * @return bool
     */
    public function remove() {
        return $this->parent->children->remove($this->key, $this->type);
    }

    /**
     * Checks if this node has or could have any children
     *
     * @return bool Returns true if it has children or could have (by AJAX expansion)
     */
    public function has_children() {
        return ($this->nodetype === orca_navigation_node::NODETYPE_BRANCH || $this->children->count()>0 || $this->isexpandable);
    }

    /**
     * Marks this node as active and forces it open.
     *
     * Important: If you are here because you need to mark a node active to get
     * the navigation to do what you want have you looked at {@link orca_navigation_node::override_active_url()}?
     * You can use it to specify a different URL to match the active navigation node on
     * rather than having to locate and manually mark a node active.
     */
    public function make_active() {
        $this->isactive = true;
        $this->add_class('active_tree_node');
        $this->force_open();
        if ($this->parent !== null) {
            $this->parent->make_inactive();
        }
    }

    /**
     * Marks a node as inactive and recusised back to the base of the tree
     * doing the same to all parents.
     */
    public function make_inactive() {
        $this->isactive = false;
        $this->remove_class('active_tree_node');
        if ($this->parent !== null) {
            $this->parent->make_inactive();
        }
    }

    /**
     * Forces this node to be open and at the same time forces open all
     * parents until the root node.
     *
     * Recursive.
     */
    public function force_open() {
        $this->forceopen = true;
        if ($this->parent !== null) {
            $this->parent->force_open();
        }
    }

    /**
     * Adds a CSS class to this node.
     *
     * @param string $class
     * @return bool
     */
    public function add_class($class) {
        if (!in_array($class, $this->classes)) {
            $this->classes[] = $class;
        }
        return true;
    }

    /**
     * Removes a CSS class from this node.
     *
     * @param string $class
     * @return bool True if the class was successfully removed.
     */
    public function remove_class($class) {
        if (in_array($class, $this->classes)) {
            $key = array_search($class,$this->classes);
            if ($key!==false) {
                // Remove the class' array element.
                unset($this->classes[$key]);
                // Reindex the array to avoid failures when the classes array is iterated later in mustache templates.
                $this->classes = array_values($this->classes);

                return true;
            }
        }
        return false;
    }

    /**
     * Sets the title for this node and forces Moodle to utilise it.
     * @param string $title
     */
    public function title($title) {
        $this->title = $title;
        $this->forcetitle = true;
    }

    /**
     * Resets the page specific information on this node if it is being unserialised.
     */
    public function __wakeup(){
        $this->forceopen = false;
        $this->isactive = false;
        $this->remove_class('active_tree_node');
    }

    /**
     * Checks if this node or any of its children contain the active node.
     *
     * Recursive.
     *
     * @return bool
     */
    public function contains_active_node() {
        if ($this->isactive) {
            return true;
        } else {
            foreach ($this->children as $child) {
                if ($child->isactive || $child->contains_active_node()) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * To better balance the admin tree, we want to group all the short top branches together.
     *
     * This means < 8 nodes and no subtrees.
     *
     * @return bool
     */
    public function is_short_branch() {
        $limit = 8;
        if ($this->children->count() >= $limit) {
            return false;
        }
        foreach ($this->children as $child) {
            if ($child->has_children()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Finds the active node.
     *
     * Searches this nodes children plus all of the children for the active node
     * and returns it if found.
     *
     * Recursive.
     *
     * @return orca_navigation_node|false
     */
    public function find_active_node() {
        if ($this->isactive) {
            return $this;
        } else {
            foreach ($this->children as &$child) {
                $outcome = $child->find_active_node();
                if ($outcome !== false) {
                    return $outcome;
                }
            }
        }
        return false;
    }

    /**
     * Searches all children for the best matching active node
     * @param int $strength The url match to be made.
     * @return orca_navigation_node|false
     */
    public function search_for_active_node($strength = URL_MATCH_BASE) {
        if ($this->check_if_active($strength)) {
            return $this;
        } else {
            foreach ($this->children as &$child) {
                $outcome = $child->search_for_active_node($strength);
                if ($outcome !== false) {
                    return $outcome;
                }
            }
        }
        return false;
    }

    /**
     * Gets the content for this node.
     *
     * @param bool $shorttext If true shorttext is used rather than the normal text
     * @return string
     */
    public function get_content($shorttext=false) {
        $navcontext = \context_helper::get_navigation_filter_context(null);
        $options = !empty($navcontext) ? ['context' => $navcontext] : null;

        if ($shorttext && $this->shorttext!==null) {
            return format_string($this->shorttext, null, $options);
        } else {
            return format_string($this->text, null, $options);
        }
    }

    /**
     * Gets the title to use for this node.
     *
     * @return string
     */
    public function get_title() {
        if ($this->forcetitle || $this->action != null){
            return $this->title;
        } else {
            return '';
        }
    }

    /**
     * Used to easily determine if this link in the breadcrumbs has a valid action/url.
     *
     * @return boolean
     */
    public function has_action() {
        return !empty($this->action);
    }

    /**
     * Used to easily determine if the action is an internal link.
     *
     * @return bool
     */
    public function has_internal_action(): bool {
        global $CFG;
        if ($this->has_action()) {
            $url = $this->action();
            if ($this->action() instanceof \action_link) {
                $url = $this->action()->url;
            }

            if (($url->out() === $CFG->wwwroot) || (strpos($url->out(), $CFG->wwwroot.'/') === 0)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Used to easily determine if this link in the breadcrumbs is hidden.
     *
     * @return boolean
     */
    public function is_hidden() {
        return $this->hidden;
    }

    /**
     * Gets the CSS class to add to this node to describe its type
     *
     * @return string
     */
    public function get_css_type() {
        if (array_key_exists($this->type, $this->namedtypes)) {
            return 'type_'.$this->namedtypes[$this->type];
        }
        return 'type_unknown';
    }

    /**
     * Finds all nodes that are expandable by AJAX
     *
     * @param array $expandable An array by reference to populate with expandable nodes.
     */
    public function find_expandable(array &$expandable) {
        foreach ($this->children as &$child) {
            if ($child->display && $child->has_children() && $child->children->count() == 0) {
                $child->id = 'expandable_branch_'.$child->type.'_'.clean_param($child->key, PARAM_ALPHANUMEXT);
                $this->add_class('canexpand');
                $child->requiresajaxloading = true;
                $expandable[] = array('id' => $child->id, 'key' => $child->key, 'type' => $child->type);
            }
            $child->find_expandable($expandable);
        }
    }

    /**
     * Finds all nodes of a given type (recursive)
     *
     * @param int $type One of orca_navigation_node::TYPE_*
     * @return array
     */
    public function find_all_of_type($type) {
        $nodes = $this->children->type($type);
        foreach ($this->children as &$node) {
            $childnodes = $node->find_all_of_type($type);
            $nodes = array_merge($nodes, $childnodes);
        }
        return $nodes;
    }

    /**
     * Removes this node if it is empty
     */
    public function trim_if_empty() {
        if ($this->children->count() == 0) {
            $this->remove();
        }
    }

    /**
     * Creates a tab representation of this nodes children that can be used
     * with print_tabs to produce the tabs on a page.
     *
     * call_user_func_array('print_tabs', $node->get_tabs_array());
     *
     * @param array $inactive
     * @param bool $return
     * @return array Array (tabs, selected, inactive, activated, return)
     */
    public function get_tabs_array(array $inactive=array(), $return=false) {
        $tabs = array();
        $rows = array();
        $selected = null;
        $activated = array();
        foreach ($this->children as $node) {
            $tabs[] = new tabobject($node->key, $node->action, $node->get_content(), $node->get_title());
            if ($node->contains_active_node()) {
                if ($node->children->count() > 0) {
                    $activated[] = $node->key;
                    foreach ($node->children as $child) {
                        if ($child->contains_active_node()) {
                            $selected = $child->key;
                        }
                        $rows[] = new tabobject($child->key, $child->action, $child->get_content(), $child->get_title());
                    }
                } else {
                    $selected = $node->key;
                }
            }
        }
        return array(array($tabs, $rows), $selected, $inactive, $activated, $return);
    }

    /**
     * Sets the parent for this node and if this node is active ensures that the tree is properly
     * adjusted as well.
     *
     * @param orca_navigation_node $parent
     */
    public function set_parent(orca_navigation_node $parent) {
        // Set the parent (thats the easy part)
        $this->parent = $parent;
        // Check if this node is active (this is checked during construction)
        if ($this->isactive) {
            // Force all of the parent nodes open so you can see this node
            $this->parent->force_open();
            // Make all parents inactive so that its clear where we are.
            $this->parent->make_inactive();
        }
    }

    /**
     * Hides the node and any children it has.
     *
     * @since Moodle 2.5
     * @param array $typestohide Optional. An array of node types that should be hidden.
     *      If null all nodes will be hidden.
     *      If an array is given then nodes will only be hidden if their type mtatches an element in the array.
     *          e.g. array(orca_navigation_node::TYPE_COURSE) would hide only course nodes.
     */
    public function hide(array $typestohide = null) {
        if ($typestohide === null || in_array($this->type, $typestohide)) {
            $this->display = false;
            if ($this->has_children()) {
                foreach ($this->children as $child) {
                    $child->hide($typestohide);
                }
            }
        }
    }

    /**
     * Get the action url for this navigation node.
     * Called from templates.
     *
     * @since Moodle 3.2
     */
    public function action() {
        if ($this->action instanceof moodle_url) {
            return $this->action;
        } else if ($this->action instanceof action_link) {
            return $this->action->url;
        }
        return $this->action;
    }

    /**
     * Return an array consisting of the additional attributes for the action url.
     *
     * @return array Formatted array to parse in a template
     */
    public function actionattributes() {
        if ($this->action instanceof action_link) {
            return array_map(function($key, $value) {
                return [
                    'name' => $key,
                    'value' => $value
                ];
            }, array_keys($this->action->attributes), $this->action->attributes);
        }

        return [];
    }

    /**
     * Check whether the node's action is of type action_link.
     *
     * @return bool
     */
    public function is_action_link() {
        return $this->action instanceof action_link;
    }

    /**
     * Return an array consisting of the actions for the action link.
     *
     * @return array Formatted array to parse in a template
     */
    public function action_link_actions() {
        global $PAGE;

        if (!$this->is_action_link()) {
            return [];
        }

        $actionid = $this->action->attributes['id'];
        $actionsdata = array_map(function($action) use ($PAGE, $actionid) {
            $data = $action->export_for_template($PAGE->get_renderer('core'));
            $data->id = $actionid;
            return $data;
        }, !empty($this->action->actions) ? $this->action->actions : []);

        return ['actions' => $actionsdata];
    }

    /**
     * Sets whether the node and its children should be added into a "more" menu whenever possible.
     *
     * @param bool $forceintomoremenu
     */
    public function set_force_into_more_menu(bool $forceintomoremenu = false) {
        $this->forceintomoremenu = $forceintomoremenu;
        foreach ($this->children as $child) {
            $child->set_force_into_more_menu($forceintomoremenu);
        }
    }

    /**
     * Sets whether the node and its children should be displayed in the "secondary" navigation when applicable.
     *
     * @param bool $show
     */
    public function set_show_in_secondary_navigation(bool $show = true) {
        $this->showinsecondarynavigation = $show;
        foreach ($this->children as $child) {
            $child->set_show_in_secondary_navigation($show);
        }
    }

    /**
     * Add the menu item to handle locking and unlocking of a conext.
     *
     * @param \orca_navigation_node $node Node to add
     * @param \context $context The context to be locked
     */
    protected function add_context_locking_node(\orca_navigation_node $node, \context $context) {
        global $CFG;
        // Manage context locking.
        if (!empty($CFG->contextlocking) && has_capability('moodle/site:managecontextlocks', $context)) {
            $parentcontext = $context->get_parent_context();
            if (empty($parentcontext) || !$parentcontext->locked) {
                if ($context->locked) {
                    $lockicon = 'i/unlock';
                    $lockstring = get_string('managecontextunlock', 'admin');
                } else {
                    $lockicon = 'i/lock';
                    $lockstring = get_string('managecontextlock', 'admin');
                }
                $node->add(
                    $lockstring,
                    new moodle_url(
                        '/admin/lock.php',
                        [
                            'id' => $context->id,
                        ]
                    ),
                    self::TYPE_SETTING,
                    null,
                    'contextlocking',
                     new pix_icon($lockicon, '')
                );
            }
        }

    }
}

/**
 * Subclass of orca_navigation_node allowing different rendering for the flat navigation
 * in particular allowing dividers and indents.
 *
 * @package   core
 * @category  navigation
 * @copyright 2016 Damyon Wiese
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class orca_flat_navigation_node extends orca_navigation_node {

    /** @var $indent integer The indent level */
    private $indent = 0;

    /** @var $showdivider bool Show a divider before this element */
    private $showdivider = false;

    /** @var $collectionlabel string Label for a group of nodes */
    private $collectionlabel = '';

    /**
     * A proxy constructor
     *
     * @param mixed $navnode A orca_navigation_node or an array
     */
    public function __construct($navnode, $indent) {
        debugging("Flat nav has been deprecated in favour of primary/secondary navigation concepts");
        if (is_array($navnode)) {
            parent::__construct($navnode);
        } else if ($navnode instanceof orca_navigation_node) {

            // Just clone everything.
            $objvalues = get_object_vars($navnode);
            foreach ($objvalues as $key => $value) {
                 $this->$key = $value;
            }
        } else {
            throw new coding_exception('Not a valid flat_navigation_node');
        }
        $this->indent = $indent;
    }

    /**
     * Setter, a label is required for a flat navigation node that shows a divider.
     *
     * @param string $label
     */
    public function set_collectionlabel($label) {
        $this->collectionlabel = $label;
    }

    /**
     * Getter, get the label for this flat_navigation node, or it's parent if it doesn't have one.
     *
     * @return string
     */
    public function get_collectionlabel() {
        if (!empty($this->collectionlabel)) {
            return $this->collectionlabel;
        }
        if ($this->parent && ($this->parent instanceof orca_flat_navigation_node || $this->parent instanceof orca_flat_navigation)) {
            return $this->parent->get_collectionlabel();
        }
        debugging('Navigation region requires a label', DEBUG_DEVELOPER);
        return '';
    }

    /**
     * Does this node represent a course section link.
     * @return boolean
     */
    public function is_section() {
        return $this->type == orca_navigation_node::TYPE_SECTION;
    }

    /**
     * In flat navigation - sections are active if we are looking at activities in the section.
     * @return boolean
     */
    public function isactive() {
        global $PAGE;

        if ($this->is_section()) {
            $active = $PAGE->navigation->find_active_node();
            if ($active) {
                while ($active = $active->parent) {
                    if ($active->key == $this->key && $active->type == $this->type) {
                        return true;
                    }
                }
            }
        }
        return $this->isactive;
    }

    /**
     * Getter for "showdivider"
     * @return boolean
     */
    public function showdivider() {
        return $this->showdivider;
    }

    /**
     * Setter for "showdivider"
     * @param $val boolean
     * @param $label string Label for the group of nodes
     */
    public function set_showdivider($val, $label = '') {
        $this->showdivider = $val;
        if ($this->showdivider && empty($label)) {
            debugging('Navigation region requires a label', DEBUG_DEVELOPER);
        } else {
            $this->set_collectionlabel($label);
        }
    }

    /**
     * Getter for "indent"
     * @return boolean
     */
    public function get_indent() {
        return $this->indent;
    }

    /**
     * Setter for "indent"
     * @param $val boolean
     */
    public function set_indent($val) {
        $this->indent = $val;
    }
}


/**
 * The name that will be used to separate the navigation cache within SESSION
 */
 
/**
 * Class used to generate a collection of navigation nodes most closely related
 * to the current page.
 *
 * @package core
 * @copyright 2016 Damyon Wiese
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


class orca_flat_navigation extends orca_navigation_node_collection {
    /** @var moodle_page the moodle page that the navigation belongs to */
    protected $page;

    /**
     * Constructor.
     *
     * @param moodle_page $page
     */
    public function __construct(moodle_page &$page) {
        if (during_initial_install()) {
            return false;
        }
        $this->page = $page;
    }

    /**
     * Build the list of navigation nodes based on the current navigation and settings trees.
     *
     */
    public function initialise() {
        global $PAGE, $USER, $OUTPUT, $CFG;
        if (during_initial_install()) {
            return;
        }

        $current = false;

        $course = $PAGE->course;
        $orca_global_navigation = new orca_global_navigation($PAGE);
        $this->page->navigation->initialise();
        $PAGE = $orca_global_navigation;
        // First walk the nav tree looking for "flat_navigation" nodes.
        if ($course->id > 1) {
            // It's a real course.
            $url = new moodle_url('/course/view.php', array('id' => $course->id));

            $coursecontext = context_course::instance($course->id, MUST_EXIST);
            $displaycontext = \context_helper::get_navigation_filter_context($coursecontext);
            // This is the name that will be shown for the course.
            $coursename = empty($CFG->navshowfullcoursenames) ?
                format_string($course->shortname, true, ['context' => $displaycontext]) :
                format_string($course->fullname, true, ['context' => $displaycontext]);

            $flat = new orca_flat_navigation_node(orca_navigation_node::create($coursename, $url), 0);
            $flat->set_collectionlabel($coursename);
            $flat->key = 'coursehome';
            $flat->icon = new pix_icon('i/course', '');

            $courseformat = course_get_format($course);
            $coursenode = $PAGE->navigation->find_active_node();
            $targettype = orca_navigation_node::TYPE_COURSE;

            // Single activity format has no course node - the course node is swapped for the activity node.
            if (!$courseformat->has_view_page()) {
                $targettype = orca_navigation_node::TYPE_ACTIVITY;
            }

            while (!empty($coursenode) && ($coursenode->type != $targettype)) {
                $coursenode = $coursenode->parent;
            }
            // There is one very strange page in mod/feedback/view.php which thinks it is both site and course
            // context at the same time. That page is broken but we need to handle it (hence the SITEID).
            if ($coursenode && $coursenode->key != SITEID) {
                $this->add($flat);
                foreach ($coursenode->children as $child) {
                    if ($child->action) {
                        $flat = new orca_flat_navigation_node($child, 0);
                        $this->add($flat);
                    }
                }
            }

            $this->page->navigation->build_flat_navigation_list($this, true, get_string('site'));
        } else {
            $this->page->navigation->build_flat_navigation_list($this, false, get_string('site'));
        }

        $admin = $PAGE->settingsnav->find('siteadministration', orca_navigation_node::TYPE_SITE_ADMIN);
        if (!$admin) {
            // Try again - crazy nav tree!
            $admin = $PAGE->settingsnav->find('root', orca_navigation_node::TYPE_SITE_ADMIN);
        }
        if ($admin) {
            $flat = new orca_flat_navigation_node($admin, 0);
            $flat->set_showdivider(true, get_string('sitesettings'));
            $flat->key = 'sitesettings';
            $flat->icon = new pix_icon('t/preferences', '');
            $this->add($flat);
        }

        // Add-a-block in editing mode.
        if (isset($this->page->theme->addblockposition) &&
                $this->page->theme->addblockposition == BLOCK_ADDBLOCK_POSITION_FLATNAV &&
                $PAGE->user_is_editing() && $PAGE->user_can_edit_blocks()) {
            $url = new moodle_url($PAGE->url, ['bui_addblock' => '', 'sesskey' => sesskey()]);
            $addablock = orca_navigation_node::create(get_string('addblock'), $url);
            $flat = new orca_flat_navigation_node($addablock, 0);
            $flat->set_showdivider(true, get_string('blocksaddedit'));
            $flat->key = 'addblock';
            $flat->icon = new pix_icon('i/addblock', '');
            $this->add($flat);

            $addblockurl = "?{$url->get_query_string(false)}";

            $PAGE->requires->js_call_amd('core/addblockmodal', 'init',
                [$PAGE->pagetype, $PAGE->pagelayout, $addblockurl]);
        }
    }

    /**
     * Override the parent so we can set a label for this collection if it has not been set yet.
     *
     * @param orca_navigation_node $node Node to add
     * @param string $beforekey If specified, adds before a node with this key,
     *   otherwise adds at end
     * @return orca_navigation_node Added node
     */
    public function add(orca_navigation_node $node, $beforekey=null) {
        $result = parent::add($node, $beforekey);
        // Extend the parent to get a name for the collection of nodes if required.
        if (empty($this->collectionlabel)) {
            if ($node instanceof orca_flat_navigation_node) {
                $this->set_collectionlabel($node->get_collectionlabel());
            }
        }

        return $result;
    }

}
/**
 * The global navigation class used for... the global navigation
 *
 * This class is used by PAGE to store the global navigation for the site
 * and is then used by the settings nav and navbar to save on processing and DB calls
 *
 * See
 * {@link lib/pagelib.php} {@link moodle_page::initialise_theme_and_output()}
 * {@link lib/ajax/getnavbranch.php} Called by ajax
 *
 * @package   core
 * @category  navigation
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class orca_global_navigation extends navigation_node {
    /** @var moodle_page The Moodle page this navigation object belongs to. */
    protected $page;
    /** @var bool switch to let us know if the navigation object is initialised*/
    protected $initialised = false;
    /** @var array An array of course information */
    protected $mycourses = array();
    /** @var navigation_node[] An array for containing  root navigation nodes */
    protected $rootnodes = array();
    /** @var bool A switch for whether to show empty sections in the navigation */
    protected $showemptysections = true;
    /** @var bool A switch for whether courses should be shown within categories on the navigation. */
    protected $showcategories = null;
    /** @var null@var bool A switch for whether or not to show categories in the my courses branch. */
    protected $showmycategories = null;
    /** @var array An array of stdClasses for users that the navigation is extended for */
    protected $extendforuser = array();
    /** @var navigation_cache */
    protected $cache;
    /** @var array An array of course ids that are present in the navigation */
    protected $addedcourses = array();
    /** @var bool */
    protected $allcategoriesloaded = false;
    /** @var array An array of category ids that are included in the navigation */
    protected $addedcategories = array();
    /** @var int expansion limit */
    protected $expansionlimit = 0;
    /** @var int userid to allow parent to see child's profile page navigation */
    protected $useridtouseforparentchecks = 0;
    /** @var cache_session A cache that stores information on expanded courses */
    protected $cacheexpandcourse = null;

    /** Used when loading categories to load all top level categories [parent = 0] **/
    const LOAD_ROOT_CATEGORIES = 0;
    /** Used when loading categories to load all categories **/
    const LOAD_ALL_CATEGORIES = -1;

    /**
     * Constructs a new global navigation
     *
     * @param moodle_page $page The page this navigation object belongs to
     */
    public function __construct(moodle_page $page) {
        global $CFG, $SITE, $USER;

        if (during_initial_install()) {
            return;
        }

        $homepage = get_home_page();
        if ($homepage == HOMEPAGE_SITE) {
            // We are using the site home for the root element.
            $properties = array(
                'key' => 'home',
                'type' => orca_navigation_node::TYPE_SYSTEM,
                'text' => get_string('home'),
                'action' => new moodle_url('/'),
                'icon' => new pix_icon('i/home', '')
            );
        } else if ($homepage == HOMEPAGE_MYCOURSES) {
            // We are using the user's course summary page for the root element.
            $properties = array(
                'key' => 'mycourses',
                'type' => orca_navigation_node::TYPE_SYSTEM,
                'text' => get_string('mycourses'),
                'action' => new moodle_url('/my/courses.php'),
                'icon' => new pix_icon('i/course', '')
            );
        } else {
            // We are using the users my moodle for the root element.
            $properties = array(
                'key' => 'myhome',
                'type' => orca_navigation_node::TYPE_SYSTEM,
                'text' => get_string('myhome'),
                'action' => new moodle_url('/my/'),
                'icon' => new pix_icon('i/dashboard', '')
            );
        }

        // Use the parents constructor.... good good reuse
        parent::__construct($properties);
        $this->showinflatnavigation = true;

        // Initalise and set defaults
        $this->page = $page;
        $this->forceopen = true;
        $this->cache = new navigation_cache(NAVIGATION_CACHE_NAME);
    }

    /**
     * Mutator to set userid to allow parent to see child's profile
     * page navigation. See MDL-25805 for initial issue. Linked to it
     * is an issue explaining why this is a REALLY UGLY HACK thats not
     * for you to use!
     *
     * @param int $userid userid of profile page that parent wants to navigate around.
     */
    public function set_userid_for_parent_checks($userid) {
        $this->useridtouseforparentchecks = $userid;
    }


    /**
     * Initialises the navigation object.
     *
     * This causes the navigation object to look at the current state of the page
     * that it is associated with and then load the appropriate content.
     *
     * This should only occur the first time that the navigation structure is utilised
     * which will normally be either when the navbar is called to be displayed or
     * when a block makes use of it.
     *
     * @return bool
     */
    public function initialise() {
        global $CFG, $SITE, $USER;
        // Check if it has already been initialised
        if ($this->initialised || during_initial_install()) {
            return true;
        }
        $this->initialised = true;

        // Set up the five base root nodes. These are nodes where we will put our
        // content and are as follows:
        // site: Navigation for the front page.
        // myprofile: User profile information goes here.
        // currentcourse: The course being currently viewed.
        // mycourses: The users courses get added here.
        // courses: Additional courses are added here.
        // users: Other users information loaded here.
        $this->rootnodes = array();
        $defaulthomepage = get_home_page();
        if ($defaulthomepage == HOMEPAGE_SITE) {
            // The home element should be my moodle because the root element is the site
            if (isloggedin() && !isguestuser()) {  // Makes no sense if you aren't logged in
                if (!empty($CFG->enabledashboard)) {
                    // Only add dashboard to home if it's enabled.
                    $this->rootnodes['home'] = $this->add(get_string('myhome'), new moodle_url('/my/'),
                        self::TYPE_SETTING, null, 'myhome', new pix_icon('i/dashboard', ''));
                    $this->rootnodes['home']->showinflatnavigation = true;
                }
            }
        } else {
            // The home element should be the site because the root node is my moodle
            $this->rootnodes['home'] = $this->add(get_string('sitehome'), new moodle_url('/'),
                self::TYPE_SETTING, null, 'home', new pix_icon('i/home', ''));
            $this->rootnodes['home']->showinflatnavigation = true;
            if (!empty($CFG->defaulthomepage) &&
                    ($CFG->defaulthomepage == HOMEPAGE_MY || $CFG->defaulthomepage == HOMEPAGE_MYCOURSES)) {
                // We need to stop automatic redirection
                $this->rootnodes['home']->action->param('redirect', '0');
            }
        }
        $this->rootnodes['site'] = $this->add_course($SITE);
        $this->rootnodes['myprofile'] = $this->add(get_string('profile'), null, self::TYPE_USER, null, 'myprofile');
        $this->rootnodes['currentcourse'] = $this->add(get_string('currentcourse'), null, self::TYPE_ROOTNODE, null, 'currentcourse');
        $this->rootnodes['mycourses'] = $this->add(
            get_string('mycourses'),
            new moodle_url('/my/courses.php'),
            self::TYPE_ROOTNODE,
            null,
            'mycourses',
            new pix_icon('i/course', '')
        );
        // We do not need to show this node in the breadcrumbs if the default homepage is mycourses.
        // It will be automatically handled by the breadcrumb generator.
        if ($defaulthomepage == HOMEPAGE_MYCOURSES) {
            $this->rootnodes['mycourses']->mainnavonly = true;
        }

        $this->rootnodes['courses'] = $this->add(get_string('courses'), new moodle_url('/course/index.php'), self::TYPE_ROOTNODE, null, 'courses');
        if (!core_course_category::user_top()) {
            $this->rootnodes['courses']->hide();
        }
        $this->rootnodes['users'] = $this->add(get_string('users'), null, self::TYPE_ROOTNODE, null, 'users');

        // We always load the frontpage course to ensure it is available without
        // JavaScript enabled.
        $this->add_front_page_course_essentials($this->rootnodes['site'], $SITE);
        $this->load_course_sections($SITE, $this->rootnodes['site']);

        $course = $this->page->course;
        $this->load_courses_enrolled();

        // $issite gets set to true if the current pages course is the sites frontpage course
        $issite = ($this->page->course->id == $SITE->id);

        // Determine if the user is enrolled in any course.
        $enrolledinanycourse = enrol_user_sees_own_courses();

        $this->rootnodes['currentcourse']->mainnavonly = true;
        if ($enrolledinanycourse) {
            $this->rootnodes['mycourses']->isexpandable = true;
            $this->rootnodes['mycourses']->showinflatnavigation = true;
            if ($CFG->navshowallcourses) {
                // When we show all courses we need to show both the my courses and the regular courses branch.
                $this->rootnodes['courses']->isexpandable = true;
            }
        } else {
            $this->rootnodes['courses']->isexpandable = true;
        }
        $this->rootnodes['mycourses']->forceopen = true;

        $canviewcourseprofile = true;

        // Next load context specific content into the navigation
        switch ($this->page->context->contextlevel) {
            case CONTEXT_SYSTEM :
                // Nothing left to do here I feel.
                break;
            case CONTEXT_COURSECAT :
                // This is essential, we must load categories.
                $this->load_all_categories($this->page->context->instanceid, true);
                break;
            case CONTEXT_BLOCK :
            case CONTEXT_COURSE :
                if ($issite) {
                    // Nothing left to do here.
                    break;
                }

                // Load the course associated with the current page into the navigation.
                $coursenode = $this->add_course($course, false, self::COURSE_CURRENT);
                // If the course wasn't added then don't try going any further.
                if (!$coursenode) {
                    $canviewcourseprofile = false;
                    break;
                }

                // If the user is not enrolled then we only want to show the
                // course node and not populate it.

                // Not enrolled, can't view, and hasn't switched roles
                if (!can_access_course($course, null, '', true)) {
                    if ($coursenode->isexpandable === true) {
                        // Obviously the situation has changed, update the cache and adjust the node.
                        // This occurs if the user access to a course has been revoked (one way or another) after
                        // initially logging in for this session.
                        $this->get_expand_course_cache()->set($course->id, 1);
                        $coursenode->isexpandable = true;
                        $coursenode->nodetype = self::NODETYPE_BRANCH;
                    }
                    // Very ugly hack - do not force "parents" to enrol into course their child is enrolled in,
                    // this hack has been propagated from user/view.php to display the navigation node. (MDL-25805)
                    if (!$this->current_user_is_parent_role()) {
                        $coursenode->make_active();
                        $canviewcourseprofile = false;
                        break;
                    }
                } else if ($coursenode->isexpandable === false) {
                    // Obviously the situation has changed, update the cache and adjust the node.
                    // This occurs if the user has been granted access to a course (one way or another) after initially
                    // logging in for this session.
                    $this->get_expand_course_cache()->set($course->id, 1);
                    $coursenode->isexpandable = true;
                    $coursenode->nodetype = self::NODETYPE_BRANCH;
                }

                // Add the essentials such as reports etc...
                $this->add_course_essentials($coursenode, $course);
                // Extend course navigation with it's sections/activities
                $this->load_course_sections($course, $coursenode);
                if (!$coursenode->contains_active_node() && !$coursenode->search_for_active_node()) {
                    $coursenode->make_active();
                }

                break;
            case CONTEXT_MODULE :
                if ($issite) {
                    // If this is the site course then most information will have
                    // already been loaded.
                    // However we need to check if there is more content that can
                    // yet be loaded for the specific module instance.
                    $activitynode = $this->rootnodes['site']->find($this->page->cm->id, navigation_node::TYPE_ACTIVITY);
                    if ($activitynode) {
                        $this->load_activity($this->page->cm, $this->page->course, $activitynode);
                    }
                    break;
                }

                $course = $this->page->course;
                $cm = $this->page->cm;

                // Load the course associated with the page into the navigation
                $coursenode = $this->add_course($course, false, self::COURSE_CURRENT);

                // If the course wasn't added then don't try going any further.
                if (!$coursenode) {
                    $canviewcourseprofile = false;
                    break;
                }

                // If the user is not enrolled then we only want to show the
                // course node and not populate it.
                if (!can_access_course($course, null, '', true)) {
                    $coursenode->make_active();
                    $canviewcourseprofile = false;
                    break;
                }

                $this->add_course_essentials($coursenode, $course);

                // Load the course sections into the page
                $this->load_course_sections($course, $coursenode, null, $cm);
                $activity = $coursenode->find($cm->id, navigation_node::TYPE_ACTIVITY);
                if (!empty($activity)) {
                    // Finally load the cm specific navigaton information
                    $this->load_activity($cm, $course, $activity);
                    // Check if we have an active ndoe
                    if (!$activity->contains_active_node() && !$activity->search_for_active_node()) {
                        // And make the activity node active.
                        $activity->make_active();
                    }
                }
                break;
            case CONTEXT_USER :
                if ($issite) {
                    // The users profile information etc is already loaded
                    // for the front page.
                    break;
                }
                $course = $this->page->course;
                // Load the course associated with the user into the navigation
                $coursenode = $this->add_course($course, false, self::COURSE_CURRENT);

                // If the course wasn't added then don't try going any further.
                if (!$coursenode) {
                    $canviewcourseprofile = false;
                    break;
                }

                // If the user is not enrolled then we only want to show the
                // course node and not populate it.
                if (!can_access_course($course, null, '', true)) {
                    $coursenode->make_active();
                    $canviewcourseprofile = false;
                    break;
                }
                $this->add_course_essentials($coursenode, $course);
                $this->load_course_sections($course, $coursenode);
                break;
        }

        // Load for the current user
        $this->load_for_user();
        if ($this->page->context->contextlevel >= CONTEXT_COURSE && $this->page->context->instanceid != $SITE->id && $canviewcourseprofile) {
            $this->load_for_user(null, true);
        }
        // Load each extending user into the navigation.
        foreach ($this->extendforuser as $user) {
            if ($user->id != $USER->id) {
                $this->load_for_user($user);
            }
        }

        // Give the local plugins a chance to include some navigation if they want.
        $this->load_local_plugin_navigation();

        // Remove any empty root nodes
        foreach ($this->rootnodes as $node) {
            // Dont remove the home node
            /** @var orca_navigation_node $node */
            if (!in_array($node->key, ['home', 'mycourses', 'myhome']) && !$node->has_children() && !$node->isactive) {
                $node->remove();
            }
        }

        if (!$this->contains_active_node()) {
            $this->search_for_active_node();
        }

        // If the user is not logged in modify the navigation structure as detailed
        // in {@link http://docs.moodle.org/dev/Navigation_2.0_structure}
        if (!isloggedin()) {
            $activities = clone($this->rootnodes['site']->children);
            $this->rootnodes['site']->remove();
            $children = clone($this->children);
            $this->children = new orca_navigation_node_collection();
            foreach ($activities as $child) {
                $this->children->add($child);
            }
            foreach ($children as $child) {
                $this->children->add($child);
            }
        }
        return true;
    }

    /**
     * This function gives local plugins an opportunity to modify navigation.
     */
    protected function load_local_plugin_navigation() {
        foreach (get_plugin_list_with_function('local', 'extend_navigation') as $function) {
            $function($this);
        }
    }

    /**
     * Returns true if the current user is a parent of the user being currently viewed.
     *
     * If the current user is not viewing another user, or if the current user does not hold any parent roles over the
     * other user being viewed this function returns false.
     * In order to set the user for whom we are checking against you must call {@link set_userid_for_parent_checks()}
     *
     * @since Moodle 2.4
     * @return bool
     */
    protected function current_user_is_parent_role() {
        global $USER, $DB;
        if ($this->useridtouseforparentchecks && $this->useridtouseforparentchecks != $USER->id) {
            $usercontext = context_user::instance($this->useridtouseforparentchecks, MUST_EXIST);
            if (!has_capability('moodle/user:viewdetails', $usercontext)) {
                return false;
            }
            if ($DB->record_exists('role_assignments', array('userid' => $USER->id, 'contextid' => $usercontext->id))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns true if courses should be shown within categories on the navigation.
     *
     * @param bool $ismycourse Set to true if you are calculating this for a course.
     * @return bool
     */
    protected function show_categories($ismycourse = false) {
        global $CFG, $DB;
        if ($ismycourse) {
            return $this->show_my_categories();
        }
        if ($this->showcategories === null) {
            $show = false;
            if ($this->page->context->contextlevel == CONTEXT_COURSECAT) {
                $show = true;
            } else if (!empty($CFG->navshowcategories) && $DB->count_records('course_categories') > 1) {
                $show = true;
            }
            $this->showcategories = $show;
        }
        return $this->showcategories;
    }

    /**
     * Returns true if we should show categories in the My Courses branch.
     * @return bool
     */
    protected function show_my_categories() {
        global $CFG;
        if ($this->showmycategories === null) {
            $this->showmycategories = !empty($CFG->navshowmycoursecategories) && !core_course_category::is_simple_site();
        }
        return $this->showmycategories;
    }

    /**
     * Loads the courses in Moodle into the navigation.
     *
     * @global moodle_database $DB
     * @param string|array $categoryids An array containing categories to load courses
     *                     for, OR null to load courses for all categories.
     * @return array An array of navigation_nodes one for each course
     */
    protected function load_all_courses($categoryids = null) {
        global $CFG, $DB, $SITE;

        // Work out the limit of courses.
        $limit = 20;
        if (!empty($CFG->navcourselimit)) {
            $limit = $CFG->navcourselimit;
        }

        $toload = (empty($CFG->navshowallcourses))?self::LOAD_ROOT_CATEGORIES:self::LOAD_ALL_CATEGORIES;

        // If we are going to show all courses AND we are showing categories then
        // to save us repeated DB calls load all of the categories now
        if ($this->show_categories()) {
            $this->load_all_categories($toload);
        }

        // Will be the return of our efforts
        $coursenodes = array();

        // Check if we need to show categories.
        if ($this->show_categories()) {
            // Hmmm we need to show categories... this is going to be painful.
            // We now need to fetch up to $limit courses for each category to
            // be displayed.
            if ($categoryids !== null) {
                if (!is_array($categoryids)) {
                    $categoryids = array($categoryids);
                }
                list($categorywhere, $categoryparams) = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'cc');
                $categorywhere = 'WHERE cc.id '.$categorywhere;
            } else if ($toload == self::LOAD_ROOT_CATEGORIES) {
                $categorywhere = 'WHERE cc.depth = 1 OR cc.depth = 2';
                $categoryparams = array();
            } else {
                $categorywhere = '';
                $categoryparams = array();
            }

            // First up we are going to get the categories that we are going to
            // need so that we can determine how best to load the courses from them.
            $sql = "SELECT cc.id, COUNT(c.id) AS coursecount
                        FROM {course_categories} cc
                    LEFT JOIN {course} c ON c.category = cc.id
                            {$categorywhere}
                    GROUP BY cc.id";
            $categories = $DB->get_recordset_sql($sql, $categoryparams);
            $fullfetch = array();
            $partfetch = array();
            foreach ($categories as $category) {
                if (!$this->can_add_more_courses_to_category($category->id)) {
                    continue;
                }
                if ($category->coursecount > $limit * 5) {
                    $partfetch[] = $category->id;
                } else if ($category->coursecount > 0) {
                    $fullfetch[] = $category->id;
                }
            }
            $categories->close();

            if (count($fullfetch)) {
                // First up fetch all of the courses in categories where we know that we are going to
                // need the majority of courses.
                list($categoryids, $categoryparams) = $DB->get_in_or_equal($fullfetch, SQL_PARAMS_NAMED, 'lcategory');
                $ccselect = ', ' . context_helper::get_preload_record_columns_sql('ctx');
                $ccjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel)";
                $categoryparams['contextlevel'] = CONTEXT_COURSE;
                $sql = "SELECT c.id, c.sortorder, c.visible, c.fullname, c.shortname, c.category $ccselect
                            FROM {course} c
                                $ccjoin
                            WHERE c.category {$categoryids}
                        ORDER BY c.sortorder ASC";
                $coursesrs = $DB->get_recordset_sql($sql, $categoryparams);
                foreach ($coursesrs as $course) {
                    if ($course->id == $SITE->id) {
                        // This should not be necessary, frontpage is not in any category.
                        continue;
                    }
                    if (array_key_exists($course->id, $this->addedcourses)) {
                        // It is probably better to not include the already loaded courses
                        // directly in SQL because inequalities may confuse query optimisers
                        // and may interfere with query caching.
                        continue;
                    }
                    if (!$this->can_add_more_courses_to_category($course->category)) {
                        continue;
                    }
                    context_helper::preload_from_record($course);
                    if (!$course->visible && !is_role_switched($course->id) && !has_capability('moodle/course:viewhiddencourses', context_course::instance($course->id))) {
                        continue;
                    }
                    $coursenodes[$course->id] = $this->add_course($course);
                }
                $coursesrs->close();
            }

            if (count($partfetch)) {
                // Next we will work our way through the categories where we will likely only need a small
                // proportion of the courses.
                foreach ($partfetch as $categoryid) {
                    $ccselect = ', ' . context_helper::get_preload_record_columns_sql('ctx');
                    $ccjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel)";
                    $sql = "SELECT c.id, c.sortorder, c.visible, c.fullname, c.shortname, c.category $ccselect
                                FROM {course} c
                                    $ccjoin
                                WHERE c.category = :categoryid
                            ORDER BY c.sortorder ASC";
                    $courseparams = array('categoryid' => $categoryid, 'contextlevel' => CONTEXT_COURSE);
                    $coursesrs = $DB->get_recordset_sql($sql, $courseparams, 0, $limit * 5);
                    foreach ($coursesrs as $course) {
                        if ($course->id == $SITE->id) {
                            // This should not be necessary, frontpage is not in any category.
                            continue;
                        }
                        if (array_key_exists($course->id, $this->addedcourses)) {
                            // It is probably better to not include the already loaded courses
                            // directly in SQL because inequalities may confuse query optimisers
                            // and may interfere with query caching.
                            // This also helps to respect expected $limit on repeated executions.
                            continue;
                        }
                        if (!$this->can_add_more_courses_to_category($course->category)) {
                            break;
                        }
                        context_helper::preload_from_record($course);
                        if (!$course->visible && !is_role_switched($course->id) && !has_capability('moodle/course:viewhiddencourses', context_course::instance($course->id))) {
                            continue;
                        }
                        $coursenodes[$course->id] = $this->add_course($course);
                    }
                    $coursesrs->close();
                }
            }
        } else {
            // Prepare the SQL to load the courses and their contexts
            list($courseids, $courseparams) = $DB->get_in_or_equal(array_keys($this->addedcourses), SQL_PARAMS_NAMED, 'lc', false);
            $ccselect = ', ' . context_helper::get_preload_record_columns_sql('ctx');
            $ccjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel)";
            $courseparams['contextlevel'] = CONTEXT_COURSE;
            $sql = "SELECT c.id, c.sortorder, c.visible, c.fullname, c.shortname, c.category $ccselect
                        FROM {course} c
                            $ccjoin
                        WHERE c.id {$courseids}
                    ORDER BY c.sortorder ASC";
            $coursesrs = $DB->get_recordset_sql($sql, $courseparams);
            foreach ($coursesrs as $course) {
                if ($course->id == $SITE->id) {
                    // frotpage is not wanted here
                    continue;
                }
                if ($this->page->course && ($this->page->course->id == $course->id)) {
                    // Don't include the currentcourse in this nodelist - it's displayed in the Current course node
                    continue;
                }
                context_helper::preload_from_record($course);
                if (!$course->visible && !is_role_switched($course->id) && !has_capability('moodle/course:viewhiddencourses', context_course::instance($course->id))) {
                    continue;
                }
                $coursenodes[$course->id] = $this->add_course($course);
                if (count($coursenodes) >= $limit) {
                    break;
                }
            }
            $coursesrs->close();
        }

        return $coursenodes;
    }

    /**
     * Returns true if more courses can be added to the provided category.
     *
     * @param int|orca_navigation_node|stdClass $category
     * @return bool
     */
    protected function can_add_more_courses_to_category($category) {
        global $CFG;
        $limit = 20;
        if (!empty($CFG->navcourselimit)) {
            $limit = (int)$CFG->navcourselimit;
        }
        if (is_numeric($category)) {
            if (!array_key_exists($category, $this->addedcategories)) {
                return true;
            }
            $coursecount = count($this->addedcategories[$category]->children->type(self::TYPE_COURSE));
        } else if ($category instanceof orca_navigation_node) {
            if (($category->type != self::TYPE_CATEGORY) || ($category->type != self::TYPE_MY_CATEGORY)) {
                return false;
            }
            $coursecount = count($category->children->type(self::TYPE_COURSE));
        } else if (is_object($category) && property_exists($category,'id')) {
            $coursecount = count($this->addedcategories[$category->id]->children->type(self::TYPE_COURSE));
        }
        return ($coursecount <= $limit);
    }

    /**
     * Loads all categories (top level or if an id is specified for that category)
     *
     * @param int $categoryid The category id to load or null/0 to load all base level categories
     * @param bool $showbasecategories If set to true all base level categories will be loaded as well
     *        as the requested category and any parent categories.
     * @return orca_navigation_node|void returns a navigation node if a category has been loaded.
     */
    protected function load_all_categories($categoryid = self::LOAD_ROOT_CATEGORIES, $showbasecategories = false) {
        global $CFG, $DB;

        // Check if this category has already been loaded
        if ($this->allcategoriesloaded || ($categoryid < 1 && $this->is_category_fully_loaded($categoryid))) {
            return true;
        }

        $catcontextsql = context_helper::get_preload_record_columns_sql('ctx');
        $sqlselect = "SELECT cc.*, $catcontextsql
                      FROM {course_categories} cc
                      JOIN {context} ctx ON cc.id = ctx.instanceid";
        $sqlwhere = "WHERE ctx.contextlevel = ".CONTEXT_COURSECAT;
        $sqlorder = "ORDER BY cc.depth ASC, cc.sortorder ASC, cc.id ASC";
        $params = array();

        $categoriestoload = array();
        if ($categoryid == self::LOAD_ALL_CATEGORIES) {
            // We are going to load all categories regardless... prepare to fire
            // on the database server!
        } else if ($categoryid == self::LOAD_ROOT_CATEGORIES) { // can be 0
            // We are going to load all of the first level categories (categories without parents)
            $sqlwhere .= " AND cc.parent = 0";
        } else if (array_key_exists($categoryid, $this->addedcategories)) {
            // The category itself has been loaded already so we just need to ensure its subcategories
            // have been loaded
            $addedcategories = $this->addedcategories;
            unset($addedcategories[$categoryid]);
            if (count($addedcategories) > 0) {
                list($sql, $params) = $DB->get_in_or_equal(array_keys($addedcategories), SQL_PARAMS_NAMED, 'parent', false);
                if ($showbasecategories) {
                    // We need to include categories with parent = 0 as well
                    $sqlwhere .= " AND (cc.parent = :categoryid OR cc.parent = 0) AND cc.parent {$sql}";
                } else {
                    // All we need is categories that match the parent
                    $sqlwhere .= " AND cc.parent = :categoryid AND cc.parent {$sql}";
                }
            }
            $params['categoryid'] = $categoryid;
        } else {
            // This category hasn't been loaded yet so we need to fetch it, work out its category path
            // and load this category plus all its parents and subcategories
            $category = $DB->get_record('course_categories', array('id' => $categoryid), 'path', MUST_EXIST);
            $categoriestoload = explode('/', trim($category->path, '/'));
            list($select, $params) = $DB->get_in_or_equal($categoriestoload);
            // We are going to use select twice so double the params
            $params = array_merge($params, $params);
            $basecategorysql = ($showbasecategories)?' OR cc.depth = 1':'';
            $sqlwhere .= " AND (cc.id {$select} OR cc.parent {$select}{$basecategorysql})";
        }

        $categoriesrs = $DB->get_recordset_sql("$sqlselect $sqlwhere $sqlorder", $params);
        $categories = array();
        foreach ($categoriesrs as $category) {
            // Preload the context.. we'll need it when adding the category in order
            // to format the category name.
            context_helper::preload_from_record($category);
            if (array_key_exists($category->id, $this->addedcategories)) {
                // Do nothing, its already been added.
            } else if ($category->parent == '0') {
                // This is a root category lets add it immediately
                $this->add_category($category, $this->rootnodes['courses']);
            } else if (array_key_exists($category->parent, $this->addedcategories)) {
                // This categories parent has already been added we can add this immediately
                $this->add_category($category, $this->addedcategories[$category->parent]);
            } else {
                $categories[] = $category;
            }
        }
        $categoriesrs->close();

        // Now we have an array of categories we need to add them to the navigation.
        while (!empty($categories)) {
            $category = reset($categories);
            if (array_key_exists($category->id, $this->addedcategories)) {
                // Do nothing
            } else if ($category->parent == '0') {
                $this->add_category($category, $this->rootnodes['courses']);
            } else if (array_key_exists($category->parent, $this->addedcategories)) {
                $this->add_category($category, $this->addedcategories[$category->parent]);
            } else {
                // This category isn't in the navigation and niether is it's parent (yet).
                // We need to go through the category path and add all of its components in order.
                $path = explode('/', trim($category->path, '/'));
                foreach ($path as $catid) {
                    if (!array_key_exists($catid, $this->addedcategories)) {
                        // This category isn't in the navigation yet so add it.
                        $subcategory = $categories[$catid];
                        if ($subcategory->parent == '0') {
                            // Yay we have a root category - this likely means we will now be able
                            // to add categories without problems.
                            $this->add_category($subcategory, $this->rootnodes['courses']);
                        } else if (array_key_exists($subcategory->parent, $this->addedcategories)) {
                            // The parent is in the category (as we'd expect) so add it now.
                            $this->add_category($subcategory, $this->addedcategories[$subcategory->parent]);
                            // Remove the category from the categories array.
                            unset($categories[$catid]);
                        } else {
                            // We should never ever arrive here - if we have then there is a bigger
                            // problem at hand.
                            throw new coding_exception('Category path order is incorrect and/or there are missing categories');
                        }
                    }
                }
            }
            // Remove the category from the categories array now that we know it has been added.
            unset($categories[$category->id]);
        }
        if ($categoryid === self::LOAD_ALL_CATEGORIES) {
            $this->allcategoriesloaded = true;
        }
        // Check if there are any categories to load.
        if (count($categoriestoload) > 0) {
            $readytoloadcourses = array();
            foreach ($categoriestoload as $category) {
                if ($this->can_add_more_courses_to_category($category)) {
                    $readytoloadcourses[] = $category;
                }
            }
            if (count($readytoloadcourses)) {
                $this->load_all_courses($readytoloadcourses);
            }
        }

        // Look for all categories which have been loaded
        if (!empty($this->addedcategories)) {
            $categoryids = array();
            foreach ($this->addedcategories as $category) {
                if ($this->can_add_more_courses_to_category($category)) {
                    $categoryids[] = $category->key;
                }
            }
            if ($categoryids) {
                list($categoriessql, $params) = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED);
                $params['limit'] = (!empty($CFG->navcourselimit))?$CFG->navcourselimit:20;
                $sql = "SELECT cc.id, COUNT(c.id) AS coursecount
                          FROM {course_categories} cc
                          JOIN {course} c ON c.category = cc.id
                         WHERE cc.id {$categoriessql}
                      GROUP BY cc.id
                        HAVING COUNT(c.id) > :limit";
                $excessivecategories = $DB->get_records_sql($sql, $params);
                foreach ($categories as &$category) {
                    if (array_key_exists($category->key, $excessivecategories) && !$this->can_add_more_courses_to_category($category)) {
                        $url = new moodle_url('/course/index.php', array('categoryid' => $category->key));
                        $category->add(get_string('viewallcourses'), $url, self::TYPE_SETTING);
                    }
                }
            }
        }
    }

    /**
     * Adds a structured category to the navigation in the correct order/place
     *
     * @param stdClass $category category to be added in navigation.
     * @param orca_navigation_node $parent parent navigation node
     * @param int $nodetype type of node, if category is under MyHome then it's TYPE_MY_CATEGORY
     * @return void.
     */
    protected function add_category(stdClass $category, orca_navigation_node $parent, $nodetype = self::TYPE_CATEGORY) {
        global $CFG;
        if (array_key_exists($category->id, $this->addedcategories)) {
            return;
        }
        $canview = core_course_category::can_view_category($category);
        $url = $canview ? new moodle_url('/course/index.php', array('categoryid' => $category->id)) : null;
        $context = \context_helper::get_navigation_filter_context(context_coursecat::instance($category->id));
        $categoryname = $canview ? format_string($category->name, true, ['context' => $context]) :
            get_string('categoryhidden');
        $categorynode = $parent->add($categoryname, $url, $nodetype, $categoryname, $category->id);
        if (!$canview) {
            // User does not have required capabilities to view category.
            $categorynode->display = false;
        } else if (!$category->visible) {
            // Category is hidden but user has capability to view hidden categories.
            $categorynode->hidden = true;
        }
        $this->addedcategories[$category->id] = $categorynode;
    }

    /**
     * Loads the given course into the navigation
     *
     * @param stdClass $course
     * @return orca_navigation_node
     */
    protected function load_course(stdClass $course) {
        global $SITE;
        if ($course->id == $SITE->id) {
            // This is always loaded during initialisation
            return $this->rootnodes['site'];
        } else if (array_key_exists($course->id, $this->addedcourses)) {
            // The course has already been loaded so return a reference
            return $this->addedcourses[$course->id];
        } else {
            // Add the course
            return $this->add_course($course);
        }
    }

    /**
     * Loads all of the courses section into the navigation.
     *
     * This function calls method from current course format, see
     * core_courseformat\base::extend_course_navigation()
     * If course module ($cm) is specified but course format failed to create the node,
     * the activity node is created anyway.
     *
     * By default course formats call the method global_navigation::load_generic_course_sections()
     *
     * @param stdClass $course Database record for the course
     * @param orca_navigation_node $coursenode The course node within the navigation
     * @param null|int $sectionnum If specified load the contents of section with this relative number
     * @param null|cm_info $cm If specified make sure that activity node is created (either
     *    in containg section or by calling load_stealth_activity() )
     */
    protected function load_course_sections(stdClass $course, navigation_node $coursenode, $sectionnum = null, $cm = null) {
        global $CFG, $SITE;
        require_once($CFG->dirroot.'/course/lib.php');
        if (isset($cm->sectionnum)) {
            $sectionnum = $cm->sectionnum;
        }
        if ($sectionnum !== null) {
            $this->includesectionnum = $sectionnum;
        }
        course_get_format($course)->extend_course_navigation($this, $coursenode, $sectionnum, $cm);
        if (isset($cm->id)) {
            $activity = $coursenode->find($cm->id, self::TYPE_ACTIVITY);
            if (empty($activity)) {
                $activity = $this->load_stealth_activity($coursenode, get_fast_modinfo($course));
            }
        }
   }

    /**
     * Generates an array of sections and an array of activities for the given course.
     *
     * This method uses the cache to improve performance and avoid the get_fast_modinfo call
     *
     * @param stdClass $course
     * @return array Array($sections, $activities)
     */
    protected function generate_sections_and_activities(stdClass $course) {
        global $CFG;
        require_once($CFG->dirroot.'/course/lib.php');

        $modinfo = get_fast_modinfo($course);
        $sections = $modinfo->get_section_info_all();

        // For course formats using 'numsections' trim the sections list
        $courseformatoptions = course_get_format($course)->get_format_options();
        if (isset($courseformatoptions['numsections'])) {
            $sections = array_slice($sections, 0, $courseformatoptions['numsections']+1, true);
        }

        $activities = array();

        foreach ($sections as $key => $section) {
            // Clone and unset summary to prevent $SESSION bloat (MDL-31802).
            $sections[$key] = clone($section);
            unset($sections[$key]->summary);
            $sections[$key]->hasactivites = false;
            if (!array_key_exists($section->section, $modinfo->sections)) {
                continue;
            }
            foreach ($modinfo->sections[$section->section] as $cmid) {
                $cm = $modinfo->cms[$cmid];
                $activity = new stdClass;
                $activity->id = $cm->id;
                $activity->course = $course->id;
                $activity->section = $section->section;
                $activity->name = $cm->name;
                $activity->icon = $cm->icon;
                $activity->iconcomponent = $cm->iconcomponent;
                $activity->hidden = (!$cm->visible);
                $activity->modname = $cm->modname;
                $activity->nodetype = orca_navigation_node::NODETYPE_LEAF;
                $activity->onclick = $cm->onclick;
                $url = $cm->url;
                if (!$url) {
                    $activity->url = null;
                    $activity->display = false;
                } else {
                    $activity->url = $url->out();
                    $activity->display = $cm->is_visible_on_course_page() ? true : false;
                    if (self::module_extends_navigation($cm->modname)) {
                        $activity->nodetype = orca_navigation_node::NODETYPE_BRANCH;
                    }
                }
                $activities[$cmid] = $activity;
                if ($activity->display) {
                    $sections[$key]->hasactivites = true;
                }
            }
        }

        return array($sections, $activities);
    }

    /**
     * Generically loads the course sections into the course's navigation.
     *
     * @param stdClass $course
     * @param orca_navigation_node $coursenode
     * @return array An array of course section nodes
     */
    public function load_generic_course_sections(stdClass $course, navigation_node $coursenode) {
        global $CFG, $DB, $USER, $SITE;
        require_once($CFG->dirroot.'/course/lib.php');

        list($sections, $activities) = $this->generate_sections_and_activities($course);

        $navigationsections = array();
        foreach ($sections as $sectionid => $section) {
            $section = clone($section);
            if ($course->id == $SITE->id) {
                $this->load_section_activities($coursenode, $section->section, $activities);
            } else {
                if (!$section->uservisible || (!$this->showemptysections &&
                        !$section->hasactivites && $this->includesectionnum !== $section->section)) {
                    continue;
                }

                $sectionname = get_section_name($course, $section);
                $url = course_get_url($course, $section->section, array('navigation' => true));

                $sectionnode = $coursenode->add($sectionname, $url, navigation_node::TYPE_SECTION,
                    null, $section->id, new pix_icon('i/section', ''));
                $sectionnode->nodetype = navigation_node::NODETYPE_BRANCH;
                $sectionnode->hidden = (!$section->visible || !$section->available);
                if ($this->includesectionnum !== false && $this->includesectionnum == $section->section) {
                    $this->load_section_activities($sectionnode, $section->section, $activities);
                }
                $section->sectionnode = $sectionnode;
                $navigationsections[$sectionid] = $section;
            }
        }
        return $navigationsections;
    }

    /**
     * Loads all of the activities for a section into the navigation structure.
     *
     * @param orca_navigation_node $sectionnode
     * @param int $sectionnumber
     * @param array $activities An array of activites as returned by {@link global_navigation::generate_sections_and_activities()}
     * @param stdClass $course The course object the section and activities relate to.
     * @return array Array of activity nodes
     */
    protected function load_section_activities(navigation_node $sectionnode, $sectionnumber, array $activities, $course = null) {
        global $CFG, $SITE;
        // A static counter for JS function naming
        static $legacyonclickcounter = 0;

        $activitynodes = array();
        if (empty($activities)) {
            return $activitynodes;
        }

        if (!is_object($course)) {
            $activity = reset($activities);
            $courseid = $activity->course;
        } else {
            $courseid = $course->id;
        }
        $showactivities = ($courseid != $SITE->id || !empty($CFG->navshowfrontpagemods));

        foreach ($activities as $activity) {
            if ($activity->section != $sectionnumber) {
                continue;
            }
            if ($activity->icon) {
                $icon = new pix_icon($activity->icon, get_string('modulename', $activity->modname), $activity->iconcomponent);
            } else {
                $icon = new pix_icon('monologo', get_string('modulename', $activity->modname), $activity->modname);
            }

            // Prepare the default name and url for the node
            $displaycontext = \context_helper::get_navigation_filter_context(context_module::instance($activity->id));
            $activityname = format_string($activity->name, true, ['context' => $displaycontext]);
            $action = new moodle_url($activity->url);

            // Check if the onclick property is set (puke!)
            if (!empty($activity->onclick)) {
                // Increment the counter so that we have a unique number.
                $legacyonclickcounter++;
                // Generate the function name we will use
                $functionname = 'legacy_activity_onclick_handler_'.$legacyonclickcounter;
                $propogrationhandler = '';
                // Check if we need to cancel propogation. Remember inline onclick
                // events would return false if they wanted to prevent propogation and the
                // default action.
                if (strpos($activity->onclick, 'return false')) {
                    $propogrationhandler = 'e.halt();';
                }
                // Decode the onclick - it has already been encoded for display (puke)
                $onclick = htmlspecialchars_decode($activity->onclick, ENT_QUOTES);
                // Build the JS function the click event will call
                $jscode = "function {$functionname}(e) { $propogrationhandler $onclick }";
                $this->page->requires->js_amd_inline($jscode);
                // Override the default url with the new action link
                $action = new action_link($action, $activityname, new component_action('click', $functionname));
            }

            $activitynode = $sectionnode->add($activityname, $action, navigation_node::TYPE_ACTIVITY, null, $activity->id, $icon);
            $activitynode->title(get_string('modulename', $activity->modname));
            $activitynode->hidden = $activity->hidden;
            $activitynode->display = $showactivities && $activity->display;
            $activitynode->nodetype = $activity->nodetype;
            $activitynodes[$activity->id] = $activitynode;
        }

        return $activitynodes;
    }
    /**
     * Loads a stealth module from unavailable section
     * @param orca_navigation_node $coursenode
     * @param stdClass $modinfo
     * @return orca_navigation_node or null if not accessible
     */
    protected function load_stealth_activity(navigation_node $coursenode, $modinfo) {
        if (empty($modinfo->cms[$this->page->cm->id])) {
            return null;
        }
        $cm = $modinfo->cms[$this->page->cm->id];
        if ($cm->icon) {
            $icon = new pix_icon($cm->icon, get_string('modulename', $cm->modname), $cm->iconcomponent);
        } else {
            $icon = new pix_icon('monologo', get_string('modulename', $cm->modname), $cm->modname);
        }
        $url = $cm->url;
        $activitynode = $coursenode->add(format_string($cm->name), $url, navigation_node::TYPE_ACTIVITY, null, $cm->id, $icon);
        $activitynode->title(get_string('modulename', $cm->modname));
        $activitynode->hidden = (!$cm->visible);
        if (!$cm->is_visible_on_course_page()) {
            // Do not show any error here, let the page handle exception that activity is not visible for the current user.
            // Also there may be no exception at all in case when teacher is logged in as student.
            $activitynode->display = false;
        } else if (!$url) {
            // Don't show activities that don't have links!
            $activitynode->display = false;
        } else if (self::module_extends_navigation($cm->modname)) {
            $activitynode->nodetype = navigation_node::NODETYPE_BRANCH;
        }
        return $activitynode;
    }
    /**
     * Loads the navigation structure for the given activity into the activities node.
     *
     * This method utilises a callback within the modules lib.php file to load the
     * content specific to activity given.
     *
     * The callback is a method: {modulename}_extend_navigation()
     * Examples:
     *  * {@link forum_extend_navigation()}
     *  * {@link workshop_extend_navigation()}
     *
     * @param cm_info|stdClass $cm
     * @param stdClass $course
     * @param orca_navigation_node $activity
     * @return bool
     */
    protected function load_activity($cm, stdClass $course, navigation_node $activity) {
        global $CFG, $DB;

        // make sure we have a $cm from get_fast_modinfo as this contains activity access details
        if (!($cm instanceof cm_info)) {
            $modinfo = get_fast_modinfo($course);
            $cm = $modinfo->get_cm($cm->id);
        }
        $activity->nodetype = navigation_node::NODETYPE_LEAF;
        $activity->make_active();
        $file = $CFG->dirroot.'/mod/'.$cm->modname.'/lib.php';
        $function = $cm->modname.'_extend_navigation';

        if (file_exists($file)) {
            require_once($file);
            if (function_exists($function)) {
                $activtyrecord = $DB->get_record($cm->modname, array('id' => $cm->instance), '*', MUST_EXIST);
                $function($activity, $course, $activtyrecord, $cm);
            }
        }

        // Allow the active advanced grading method plugin to append module navigation
        $featuresfunc = $cm->modname.'_supports';
        if (function_exists($featuresfunc) && $featuresfunc(FEATURE_ADVANCED_GRADING)) {
            require_once($CFG->dirroot.'/grade/grading/lib.php');
            $gradingman = get_grading_manager($cm->context,  'mod_'.$cm->modname);
            $gradingman->extend_navigation($this, $activity);
        }

        return $activity->has_children();
    }
    /**
     * Loads user specific information into the navigation in the appropriate place.
     *
     * If no user is provided the current user is assumed.
     *
     * @param stdClass $user
     * @param bool $forceforcontext probably force something to be loaded somewhere (ask SamH if not sure what this means)
     * @return bool
     */
    protected function load_for_user($user=null, $forceforcontext=false) {
        global $DB, $CFG, $USER, $SITE;

        require_once($CFG->dirroot . '/course/lib.php');

        if ($user === null) {
            // We can't require login here but if the user isn't logged in we don't
            // want to show anything
            if (!isloggedin() || isguestuser()) {
                return false;
            }
            $user = $USER;
        } else if (!is_object($user)) {
            // If the user is not an object then get them from the database
            $select = context_helper::get_preload_record_columns_sql('ctx');
            $sql = "SELECT u.*, $select
                      FROM {user} u
                      JOIN {context} ctx ON u.id = ctx.instanceid
                     WHERE u.id = :userid AND
                           ctx.contextlevel = :contextlevel";
            $user = $DB->get_record_sql($sql, array('userid' => (int)$user, 'contextlevel' => CONTEXT_USER), MUST_EXIST);
            context_helper::preload_from_record($user);
        }

        $iscurrentuser = ($user->id == $USER->id);

        $usercontext = context_user::instance($user->id);

        // Get the course set against the page, by default this will be the site
        $course = $this->page->course;
        $baseargs = array('id'=>$user->id);
        if ($course->id != $SITE->id && (!$iscurrentuser || $forceforcontext)) {
            $coursenode = $this->add_course($course, false, self::COURSE_CURRENT);
            $baseargs['course'] = $course->id;
            $coursecontext = context_course::instance($course->id);
            $issitecourse = false;
        } else {
            // Load all categories and get the context for the system
            $coursecontext = context_system::instance();
            $issitecourse = true;
        }

        // Create a node to add user information under.
        $usersnode = null;
        if (!$issitecourse) {
            // Not the current user so add it to the participants node for the current course.
            $usersnode = $coursenode->get('participants', orca_navigation_node::TYPE_CONTAINER);
            $userviewurl = new moodle_url('/user/view.php', $baseargs);
        } else if ($USER->id != $user->id) {
            // This is the site so add a users node to the root branch.
            $usersnode = $this->rootnodes['users'];
            if (course_can_view_participants($coursecontext)) {
                $usersnode->action = new moodle_url('/user/index.php', array('id' => $course->id));
            }
            $userviewurl = new moodle_url('/user/profile.php', $baseargs);
        }
        if (!$usersnode) {
            // We should NEVER get here, if the course hasn't been populated
            // with a participants node then the navigaiton either wasn't generated
            // for it (you are missing a require_login or set_context call) or
            // you don't have access.... in the interests of no leaking informatin
            // we simply quit...
            return false;
        }
        // Add a branch for the current user.
        // Only reveal user details if $user is the current user, or a user to which the current user has access.
        $viewprofile = true;
        if (!$iscurrentuser) {
            require_once($CFG->dirroot . '/user/lib.php');
            if ($this->page->context->contextlevel == CONTEXT_USER && !has_capability('moodle/user:viewdetails', $usercontext) ) {
                $viewprofile = false;
            } else if ($this->page->context->contextlevel != CONTEXT_USER && !user_can_view_profile($user, $course, $usercontext)) {
                $viewprofile = false;
            }
            if (!$viewprofile) {
                $viewprofile = user_can_view_profile($user, null, $usercontext);
            }
        }

        // Now, conditionally add the user node.
        if ($viewprofile) {
            $canseefullname = has_capability('moodle/site:viewfullnames', $coursecontext);
            $usernode = $usersnode->add(fullname($user, $canseefullname), $userviewurl, self::TYPE_USER, null, 'user' . $user->id);
        } else {
            $usernode = $usersnode->add(get_string('user'));
        }

        if ($this->page->context->contextlevel == CONTEXT_USER && $user->id == $this->page->context->instanceid) {
            $usernode->make_active();
        }

        // Add user information to the participants or user node.
        if ($issitecourse) {

            // If the user is the current user or has permission to view the details of the requested
            // user than add a view profile link.
            if ($iscurrentuser || has_capability('moodle/user:viewdetails', $coursecontext) ||
                    has_capability('moodle/user:viewdetails', $usercontext)) {
                if ($issitecourse || ($iscurrentuser && !$forceforcontext)) {
                    $usernode->add(get_string('viewprofile'), new moodle_url('/user/profile.php', $baseargs));
                } else {
                    $usernode->add(get_string('viewprofile'), new moodle_url('/user/view.php', $baseargs));
                }
            }

            if (!empty($CFG->navadduserpostslinks)) {
                // Add nodes for forum posts and discussions if the user can view either or both
                // There are no capability checks here as the content of the page is based
                // purely on the forums the current user has access too.
                $forumtab = $usernode->add(get_string('forumposts', 'forum'));
                $forumtab->add(get_string('posts', 'forum'), new moodle_url('/mod/forum/user.php', $baseargs));
                $forumtab->add(get_string('discussions', 'forum'), new moodle_url('/mod/forum/user.php',
                        array_merge($baseargs, array('mode' => 'discussions'))));
            }

            // Add blog nodes.
            if (!empty($CFG->enableblogs)) {
                if (!$this->cache->cached('userblogoptions'.$user->id)) {
                    require_once($CFG->dirroot.'/blog/lib.php');
                    // Get all options for the user.
                    $options = blog_get_options_for_user($user);
                    $this->cache->set('userblogoptions'.$user->id, $options);
                } else {
                    $options = $this->cache->{'userblogoptions'.$user->id};
                }

                if (count($options) > 0) {
                    $blogs = $usernode->add(get_string('blogs', 'blog'), null, orca_navigation_node::TYPE_CONTAINER);
                    foreach ($options as $type => $option) {
                        if ($type == "rss") {
                            $blogs->add($option['string'], $option['link'], settings_navigation::TYPE_SETTING, null, null,
                                    new pix_icon('i/rss', ''));
                        } else {
                            $blogs->add($option['string'], $option['link']);
                        }
                    }
                }
            }

            // Add the messages link.
            // It is context based so can appear in the user's profile and in course participants information.
            if (!empty($CFG->messaging)) {
                $messageargs = array('user1' => $USER->id);
                if ($USER->id != $user->id) {
                    $messageargs['user2'] = $user->id;
                }
                $url = new moodle_url('/message/index.php', $messageargs);
                $usernode->add(get_string('messages', 'message'), $url, self::TYPE_SETTING, null, 'messages');
            }

            // Add the "My private files" link.
            // This link doesn't have a unique display for course context so only display it under the user's profile.
            if ($issitecourse && $iscurrentuser && has_capability('moodle/user:manageownfiles', $usercontext)) {
                $url = new moodle_url('/user/files.php');
                $usernode->add(get_string('privatefiles'), $url, self::TYPE_SETTING, null, 'privatefiles');
            }

            // Add a node to view the users notes if permitted.
            if (!empty($CFG->enablenotes) &&
                    has_any_capability(array('moodle/notes:manage', 'moodle/notes:view'), $coursecontext)) {
                $url = new moodle_url('/notes/index.php', array('user' => $user->id));
                if ($coursecontext->instanceid != SITEID) {
                    $url->param('course', $coursecontext->instanceid);
                }
                $usernode->add(get_string('notes', 'notes'), $url);
            }

            // Show the grades node.
            if (($issitecourse && $iscurrentuser) || has_capability('moodle/user:viewdetails', $usercontext)) {
                require_once($CFG->dirroot . '/user/lib.php');
                // Set the grades node to link to the "Grades" page.
                if ($course->id == SITEID) {
                    $url = user_mygrades_url($user->id, $course->id);
                } else { // Otherwise we are in a course and should redirect to the user grade report (Activity report version).
                    $url = new moodle_url('/course/user.php', array('mode' => 'grade', 'id' => $course->id, 'user' => $user->id));
                }
                if ($USER->id != $user->id) {
                    $usernode->add(get_string('grades', 'grades'), $url, self::TYPE_SETTING, null, 'usergrades');
                } else {
                    $usernode->add(get_string('grades', 'grades'), $url);
                }
            }

            // If the user is the current user add the repositories for the current user.
            $hiddenfields = array_flip(explode(',', $CFG->hiddenuserfields));
            if (!$iscurrentuser &&
                    $course->id == $SITE->id &&
                    has_capability('moodle/user:viewdetails', $usercontext) &&
                    (!in_array('mycourses', $hiddenfields) || has_capability('moodle/user:viewhiddendetails', $coursecontext))) {

                // Add view grade report is permitted.
                $reports = core_component::get_plugin_list('gradereport');
                arsort($reports); // User is last, we want to test it first.

                $userscourses = enrol_get_users_courses($user->id, false, '*');
                $userscoursesnode = $usernode->add(get_string('courses'));

                $count = 0;
                foreach ($userscourses as $usercourse) {
                    if ($count === (int)$CFG->navcourselimit) {
                        $url = new moodle_url('/user/profile.php', array('id' => $user->id, 'showallcourses' => 1));
                        $userscoursesnode->add(get_string('showallcourses'), $url);
                        break;
                    }
                    $count++;
                    $usercoursecontext = context_course::instance($usercourse->id);
                    $usercourseshortname = format_string($usercourse->shortname, true, array('context' => $usercoursecontext));
                    $usercoursenode = $userscoursesnode->add($usercourseshortname, new moodle_url('/user/view.php',
                            array('id' => $user->id, 'course' => $usercourse->id)), self::TYPE_CONTAINER);

                    $gradeavailable = has_capability('moodle/grade:view', $usercoursecontext);
                    if (!$gradeavailable && !empty($usercourse->showgrades) && is_array($reports) && !empty($reports)) {
                        foreach ($reports as $plugin => $plugindir) {
                            if (has_capability('gradereport/'.$plugin.':view', $usercoursecontext)) {
                                // Stop when the first visible plugin is found.
                                $gradeavailable = true;
                                break;
                            }
                        }
                    }

                    if ($gradeavailable) {
                        $url = new moodle_url('/grade/report/index.php', array('id' => $usercourse->id));
                        $usercoursenode->add(get_string('grades'), $url, self::TYPE_SETTING, null, null,
                                new pix_icon('i/grades', ''));
                    }

                    // Add a node to view the users notes if permitted.
                    if (!empty($CFG->enablenotes) &&
                            has_any_capability(array('moodle/notes:manage', 'moodle/notes:view'), $usercoursecontext)) {
                        $url = new moodle_url('/notes/index.php', array('user' => $user->id, 'course' => $usercourse->id));
                        $usercoursenode->add(get_string('notes', 'notes'), $url, self::TYPE_SETTING);
                    }

                    if (can_access_course($usercourse, $user->id, '', true)) {
                        $usercoursenode->add(get_string('entercourse'), new moodle_url('/course/view.php',
                                array('id' => $usercourse->id)), self::TYPE_SETTING, null, null, new pix_icon('i/course', ''));
                    }

                    $reporttab = $usercoursenode->add(get_string('activityreports'));

                    $reportfunctions = get_plugin_list_with_function('report', 'extend_navigation_user', 'lib.php');
                    foreach ($reportfunctions as $reportfunction) {
                        $reportfunction($reporttab, $user, $usercourse);
                    }

                    $reporttab->trim_if_empty();
                }
            }

            // Let plugins hook into user navigation.
            $pluginsfunction = get_plugins_with_function('extend_navigation_user', 'lib.php');
            foreach ($pluginsfunction as $plugintype => $plugins) {
                if ($plugintype != 'report') {
                    foreach ($plugins as $pluginfunction) {
                        $pluginfunction($usernode, $user, $usercontext, $course, $coursecontext);
                    }
                }
            }
        }
        return true;
    }

    /**
     * This method simply checks to see if a given module can extend the navigation.
     *
     * @todo (MDL-25290) A shared caching solution should be used to save details on what extends navigation.
     *
     * @param string $modname
     * @return bool
     */
    public static function module_extends_navigation($modname) {
        global $CFG;
        static $extendingmodules = array();
        if (!array_key_exists($modname, $extendingmodules)) {
            $extendingmodules[$modname] = false;
            $file = $CFG->dirroot.'/mod/'.$modname.'/lib.php';
            if (file_exists($file)) {
                $function = $modname.'_extend_navigation';
                require_once($file);
                $extendingmodules[$modname] = (function_exists($function));
            }
        }
        return $extendingmodules[$modname];
    }
    /**
     * Extends the navigation for the given user.
     *
     * @param stdClass $user A user from the database
     */
    public function extend_for_user($user) {
        $this->extendforuser[] = $user;
    }

    /**
     * Returns all of the users the navigation is being extended for
     *
     * @return array An array of extending users.
     */
    public function get_extending_users() {
        return $this->extendforuser;
    }
    /**
     * Adds the given course to the navigation structure.
     *
     * @param stdClass $course
     * @param bool $forcegeneric
     * @param bool $ismycourse
     * @return orca_navigation_node
     */
    public function add_course(stdClass $course, $forcegeneric = false, $coursetype = self::COURSE_OTHER) {
        global $CFG, $SITE;

        // We found the course... we can return it now :)
        if (!$forcegeneric && array_key_exists($course->id, $this->addedcourses)) {
            return $this->addedcourses[$course->id];
        }

        $coursecontext = context_course::instance($course->id);

        if ($coursetype != self::COURSE_MY && $coursetype != self::COURSE_CURRENT && $course->id != $SITE->id) {
            if (is_role_switched($course->id)) {
                // user has to be able to access course in order to switch, let's skip the visibility test here
            } else if (!core_course_category::can_view_course_info($course)) {
                return false;
            }
        }

        $issite = ($course->id == $SITE->id);
        $displaycontext = \context_helper::get_navigation_filter_context($coursecontext);
        $shortname = format_string($course->shortname, true, ['context' => $displaycontext]);
        $fullname = format_string($course->fullname, true, ['context' => $displaycontext]);
        // This is the name that will be shown for the course.
        $coursename = empty($CFG->navshowfullcoursenames) ? $shortname : $fullname;

        if ($coursetype == self::COURSE_CURRENT) {
            if ($coursenode = $this->rootnodes['mycourses']->find($course->id, self::TYPE_COURSE)) {
                return $coursenode;
            } else {
                $coursetype = self::COURSE_OTHER;
            }
        }

        // Can the user expand the course to see its content.
        $canexpandcourse = true;
        if ($issite) {
            $parent = $this;
            $url = null;
            if (empty($CFG->usesitenameforsitepages)) {
                $coursename = get_string('sitepages');
            }
        } else if ($coursetype == self::COURSE_CURRENT) {
            $parent = $this->rootnodes['currentcourse'];
            $url = new moodle_url('/course/view.php', array('id'=>$course->id));
            $canexpandcourse = $this->can_expand_course($course);
        } else if ($coursetype == self::COURSE_MY && !$forcegeneric) {
            if (!empty($CFG->navshowmycoursecategories) && ($parent = $this->rootnodes['mycourses']->find($course->category, self::TYPE_MY_CATEGORY))) {
                // Nothing to do here the above statement set $parent to the category within mycourses.
            } else {
                $parent = $this->rootnodes['mycourses'];
            }
            $url = new moodle_url('/course/view.php', array('id'=>$course->id));
        } else {
            $parent = $this->rootnodes['courses'];
            $url = new moodle_url('/course/view.php', array('id'=>$course->id));
            // They can only expand the course if they can access it.
            $canexpandcourse = $this->can_expand_course($course);
            if (!empty($course->category) && $this->show_categories($coursetype == self::COURSE_MY)) {
                if (!$this->is_category_fully_loaded($course->category)) {
                    // We need to load the category structure for this course
                    $this->load_all_categories($course->category, false);
                }
                if (array_key_exists($course->category, $this->addedcategories)) {
                    $parent = $this->addedcategories[$course->category];
                    // This could lead to the course being created so we should check whether it is the case again
                    if (!$forcegeneric && array_key_exists($course->id, $this->addedcourses)) {
                        return $this->addedcourses[$course->id];
                    }
                }
            }
        }

        $coursenode = $parent->add($coursename, $url, self::TYPE_COURSE, $shortname, $course->id, new pix_icon('i/course', ''));
        $coursenode->showinflatnavigation = $coursetype == self::COURSE_MY;

        $coursenode->hidden = (!$course->visible);
        $coursenode->title(format_string($course->fullname, true, ['context' => $displaycontext, 'escape' => false]));
        if ($canexpandcourse) {
            // This course can be expanded by the user, make it a branch to make the system aware that its expandable by ajax.
            $coursenode->nodetype = self::NODETYPE_BRANCH;
            $coursenode->isexpandable = true;
        } else {
            $coursenode->nodetype = self::NODETYPE_LEAF;
            $coursenode->isexpandable = false;
        }
        if (!$forcegeneric) {
            $this->addedcourses[$course->id] = $coursenode;
        }

        return $coursenode;
    }

    /**
     * Returns a cache instance to use for the expand course cache.
     * @return cache_session
     */
    protected function get_expand_course_cache() {
        if ($this->cacheexpandcourse === null) {
            $this->cacheexpandcourse = cache::make('core', 'navigation_expandcourse');
        }
        return $this->cacheexpandcourse;
    }

    /**
     * Checks if a user can expand a course in the navigation.
     *
     * We use a cache here because in order to be accurate we need to call can_access_course which is a costly function.
     * Because this functionality is basic + non-essential and because we lack good event triggering this cache
     * permits stale data.
     * In the situation the user is granted access to a course after we've initialised this session cache the cache
     * will be stale.
     * It is brought up to date in only one of two ways.
     *   1. The user logs out and in again.
     *   2. The user browses to the course they've just being given access to.
     *
     * Really all this controls is whether the node is shown as expandable or not. It is uber un-important.
     *
     * @param stdClass $course
     * @return bool
     */
    protected function can_expand_course($course) {
        $cache = $this->get_expand_course_cache();
        $canexpand = $cache->get($course->id);
        if ($canexpand === false) {
            $canexpand = isloggedin() && can_access_course($course, null, '', true);
            $canexpand = (int)$canexpand;
            $cache->set($course->id, $canexpand);
        }
        return ($canexpand === 1);
    }

    /**
     * Returns true if the category has already been loaded as have any child categories
     *
     * @param int $categoryid
     * @return bool
     */
    protected function is_category_fully_loaded($categoryid) {
        return (array_key_exists($categoryid, $this->addedcategories) && ($this->allcategoriesloaded || $this->addedcategories[$categoryid]->children->count() > 0));
    }

    /**
     * Adds essential course nodes to the navigation for the given course.
     *
     * This method adds nodes such as reports, blogs and participants
     *
     * @param orca_navigation_node $coursenode
     * @param stdClass $course
     * @return bool returns true on successful addition of a node.
     */
    public function add_course_essentials($coursenode, stdClass $course) {
        global $CFG, $SITE;
        require_once($CFG->dirroot . '/course/lib.php');

        if ($course->id == $SITE->id) {
            return $this->add_front_page_course_essentials($coursenode, $course);
        }

        if ($coursenode == false || !($coursenode instanceof orca_navigation_node) || $coursenode->get('participants', orca_navigation_node::TYPE_CONTAINER)) {
            return true;
        }

        $navoptions = course_get_user_navigation_options($this->page->context, $course);

        //Participants
        if ($navoptions->participants) {
            $participants = $coursenode->add(get_string('participants'), new moodle_url('/user/index.php?id='.$course->id),
                self::TYPE_CONTAINER, get_string('participants'), 'participants', new pix_icon('i/users', ''));

            if ($navoptions->blogs) {
                $blogsurls = new moodle_url('/blog/index.php');
                if ($currentgroup = groups_get_course_group($course, true)) {
                    $blogsurls->param('groupid', $currentgroup);
                } else {
                    $blogsurls->param('courseid', $course->id);
                }
                $participants->add(get_string('blogscourse', 'blog'), $blogsurls->out(), self::TYPE_SETTING, null, 'courseblogs');
            }

            if ($navoptions->notes) {
                $participants->add(get_string('notes', 'notes'), new moodle_url('/notes/index.php', array('filtertype' => 'course', 'filterselect' => $course->id)), self::TYPE_SETTING, null, 'currentcoursenotes');
            }
        } else if (count($this->extendforuser) > 0) {
            $coursenode->add(get_string('participants'), null, self::TYPE_CONTAINER, get_string('participants'), 'participants');
        }

        // Badges.
        if ($navoptions->badges) {
            $url = new moodle_url('/badges/view.php', array('type' => 2, 'id' => $course->id));

            $coursenode->add(get_string('coursebadges', 'badges'), $url,
                    orca_navigation_node::TYPE_SETTING, null, 'badgesview',
                    new pix_icon('i/badge', get_string('coursebadges', 'badges')));
        }

        // Check access to the course and competencies page.
        if ($navoptions->competencies) {
            // Just a link to course competency.
            $title = get_string('competencies', 'core_competency');
            $path = new moodle_url("/admin/tool/lp/coursecompetencies.php", array('courseid' => $course->id));
            $coursenode->add($title, $path, orca_navigation_node::TYPE_SETTING, null, 'competencies',
                    new pix_icon('i/competencies', ''));
        }
        if ($navoptions->grades) {
            $url = new moodle_url('/grade/report/index.php', array('id'=>$course->id));
            $gradenode = $coursenode->add(get_string('grades'), $url, self::TYPE_SETTING, null,
                'grades', new pix_icon('i/grades', ''));
            // If the page type matches the grade part, then make the nav drawer grade node (incl. all sub pages) active.
            if ($this->page->context->contextlevel < CONTEXT_MODULE && strpos($this->page->pagetype, 'grade-') === 0) {
                $gradenode->make_active();
            }
        }

        return true;
    }
    /**
     * This generates the structure of the course that won't be generated when
     * the modules and sections are added.
     *
     * Things such as the reports branch, the participants branch, blogs... get
     * added to the course node by this method.
     *
     * @param orca_navigation_node $coursenode
     * @param stdClass $course
     * @return bool True for successfull generation
     */
    public function add_front_page_course_essentials(navigation_node $coursenode, stdClass $course) {
        global $CFG, $USER, $COURSE, $SITE;
        require_once($CFG->dirroot . '/course/lib.php');

        if ($coursenode == false || $coursenode->get('frontpageloaded', navigation_node::TYPE_CUSTOM)) {
            return true;
        }

        $systemcontext = context_system::instance();
        $navoptions = course_get_user_navigation_options($systemcontext, $course);

        // Hidden node that we use to determine if the front page navigation is loaded.
        // This required as there are not other guaranteed nodes that may be loaded.
        $coursenode->add('frontpageloaded', null, self::TYPE_CUSTOM, null, 'frontpageloaded')->display = false;

        // Add My courses to the site pages within the navigation structure so the block can read it.
        $coursenode->add(get_string('mycourses'), new moodle_url('/my/courses.php'), self::TYPE_CUSTOM, null, 'mycourses');

        // Participants.
        if ($navoptions->participants) {
            $coursenode->add(get_string('participants'), new moodle_url('/user/index.php?id='.$course->id),
                self::TYPE_CUSTOM, get_string('participants'), 'participants');
        }

        // Blogs.
        if ($navoptions->blogs) {
            $blogsurls = new moodle_url('/blog/index.php');
            $coursenode->add(get_string('blogssite', 'blog'), $blogsurls->out(), self::TYPE_SYSTEM, null, 'siteblog');
        }

        $filterselect = 0;

        // Badges.
        if ($navoptions->badges) {
            $url = new moodle_url($CFG->wwwroot . '/badges/view.php', array('type' => 1));
            $coursenode->add(get_string('sitebadges', 'badges'), $url, navigation_node::TYPE_CUSTOM);
        }

        // Notes.
        if ($navoptions->notes) {
            $coursenode->add(get_string('notes', 'notes'), new moodle_url('/notes/index.php',
                array('filtertype' => 'course', 'filterselect' => $filterselect)), self::TYPE_SETTING, null, 'notes');
        }

        // Tags
        if ($navoptions->tags) {
            $node = $coursenode->add(get_string('tags', 'tag'), new moodle_url('/tag/search.php'),
                    self::TYPE_SETTING, null, 'tags');
        }

        // Search.
        if ($navoptions->search) {
            $node = $coursenode->add(get_string('search', 'search'), new moodle_url('/search/index.php'),
                    self::TYPE_SETTING, null, 'search');
        }

        if (isloggedin()) {
            $usercontext = context_user::instance($USER->id);
            if (has_capability('moodle/user:manageownfiles', $usercontext)) {
                $url = new moodle_url('/user/files.php');
                $node = $coursenode->add(get_string('privatefiles'), $url,
                    self::TYPE_SETTING, null, 'privatefiles', new pix_icon('i/privatefiles', ''));
                $node->display = false;
                $node->showinflatnavigation = true;
                $node->mainnavonly = true;
            }
        }

        if (isloggedin()) {
            $context = $this->page->context;
            switch ($context->contextlevel) {
                case CONTEXT_COURSECAT:
                    // OK, expected context level.
                    break;
                case CONTEXT_COURSE:
                    // OK, expected context level if not on frontpage.
                    if ($COURSE->id != $SITE->id) {
                        break;
                    }
                default:
                    // If this context is part of a course (excluding frontpage), use the course context.
                    // Otherwise, use the system context.
                    $coursecontext = $context->get_course_context(false);
                    if ($coursecontext && $coursecontext->instanceid !== $SITE->id) {
                        $context = $coursecontext;
                    } else {
                        $context = $systemcontext;
                    }
            }

            $params = ['contextid' => $context->id];
            if (has_capability('moodle/contentbank:access', $context)) {
                $url = new moodle_url('/contentbank/index.php', $params);
                $node = $coursenode->add(get_string('contentbank'), $url,
                    self::TYPE_CUSTOM, null, 'contentbank', new pix_icon('i/contentbank', ''));
                $node->showinflatnavigation = true;
            }
        }

        return true;
    }

    /**
     * Clears the navigation cache
     */
    public function clear_cache() {
        $this->cache->clear();
    }

    /**
     * Sets an expansion limit for the navigation
     *
     * The expansion limit is used to prevent the display of content that has a type
     * greater than the provided $type.
     *
     * Can be used to ensure things such as activities or activity content don't get
     * shown on the navigation.
     * They are still generated in order to ensure the navbar still makes sense.
     *
     * @param int $type One of orca_navigation_node::TYPE_*
     * @return bool true when complete.
     */
    public function set_expansion_limit($type) {
        global $SITE;
        $nodes = $this->find_all_of_type($type);

        // We only want to hide specific types of nodes.
        // Only nodes that represent "structure" in the navigation tree should be hidden.
        // If we hide all nodes then we risk hiding vital information.
        $typestohide = array(
            self::TYPE_CATEGORY,
            self::TYPE_COURSE,
            self::TYPE_SECTION,
            self::TYPE_ACTIVITY
        );

        foreach ($nodes as $node) {
            // We need to generate the full site node
            if ($type == self::TYPE_COURSE && $node->key == $SITE->id) {
                continue;
            }
            foreach ($node->children as $child) {
                $child->hide($typestohide);
            }
        }
        return true;
    }
    /**
     * Attempts to get the navigation with the given key from this nodes children.
     *
     * This function only looks at this nodes children, it does NOT look recursivily.
     * If the node can't be found then false is returned.
     *
     * If you need to search recursivily then use the {@link global_navigation::find()} method.
     *
     * Note: If you are trying to set the active node {@link orca_navigation_node::override_active_url()}
     * may be of more use to you.
     *
     * @param string|int $key The key of the node you wish to receive.
     * @param int $type One of orca_navigation_node::TYPE_*
     * @return orca_navigation_node|false
     */
    public function get($key, $type = null) {
        if (!$this->initialised) {
            $this->initialise();
        }
        return parent::get($key, $type);
    }

    /**
     * Searches this nodes children and their children to find a navigation node
     * with the matching key and type.
     *
     * This method is recursive and searches children so until either a node is
     * found or there are no more nodes to search.
     *
     * If you know that the node being searched for is a child of this node
     * then use the {@link global_navigation::get()} method instead.
     *
     * Note: If you are trying to set the active node {@link orca_navigation_node::override_active_url()}
     * may be of more use to you.
     *
     * @param string|int $key The key of the node you wish to receive.
     * @param int $type One of orca_navigation_node::TYPE_*
     * @return orca_navigation_node|false
     */
    public function find($key, $type) {
        if (!$this->initialised) {
            $this->initialise();
        }
        if ($type == self::TYPE_ROOTNODE && array_key_exists($key, $this->rootnodes)) {
            return $this->rootnodes[$key];
        }
        return parent::find($key, $type);
    }

    /**
     * They've expanded the 'my courses' branch.
     */
    protected function load_courses_enrolled() {
        global $CFG;

        $limit = (int) $CFG->navcourselimit;

        $courses = enrol_get_my_courses('*');
        $flatnavcourses = [];

        // Go through the courses and see which ones we want to display in the flatnav.
        foreach ($courses as $course) {
            $classify = course_classify_for_timeline($course);

            if ($classify == COURSE_TIMELINE_INPROGRESS) {
                $flatnavcourses[$course->id] = $course;
            }
        }

        // Get the number of courses that can be displayed in the nav block and in the flatnav.
        $numtotalcourses = count($courses);
        $numtotalflatnavcourses = count($flatnavcourses);

        // Reduce the size of the arrays to abide by the 'navcourselimit' setting.
        $courses = array_slice($courses, 0, $limit, true);
        $flatnavcourses = array_slice($flatnavcourses, 0, $limit, true);

        // Get the number of courses we are going to show for each.
        $numshowncourses = count($courses);
        $numshownflatnavcourses = count($flatnavcourses);
        if ($numshowncourses && $this->show_my_categories()) {
            // Generate an array containing unique values of all the courses' categories.
            $categoryids = array();
            foreach ($courses as $course) {
                if (in_array($course->category, $categoryids)) {
                    continue;
                }
                $categoryids[] = $course->category;
            }

            // Array of category IDs that include the categories of the user's courses and the related course categories.
            $fullpathcategoryids = [];
            // Get the course categories for the enrolled courses' category IDs.
            $mycoursecategories = core_course_category::get_many($categoryids);
            // Loop over each of these categories and build the category tree using each category's path.
            foreach ($mycoursecategories as $mycoursecat) {
                $pathcategoryids = explode('/', $mycoursecat->path);
                // First element of the exploded path is empty since paths begin with '/'.
                array_shift($pathcategoryids);
                // Merge the exploded category IDs into the full list of category IDs that we will fetch.
                $fullpathcategoryids = array_merge($fullpathcategoryids, $pathcategoryids);
            }

            // Fetch all of the categories related to the user's courses.
            $pathcategories = core_course_category::get_many($fullpathcategoryids);
            // Loop over each of these categories and build the category tree.
            foreach ($pathcategories as $coursecat) {
                // No need to process categories that have already been added.
                if (isset($this->addedcategories[$coursecat->id])) {
                    continue;
                }
                // Skip categories that are not visible.
                if (!$coursecat->is_uservisible()) {
                    continue;
                }

                // Get this course category's parent node.
                $parent = null;
                if ($coursecat->parent && isset($this->addedcategories[$coursecat->parent])) {
                    $parent = $this->addedcategories[$coursecat->parent];
                }
                if (!$parent) {
                    // If it has no parent, then it should be right under the My courses node.
                    $parent = $this->rootnodes['mycourses'];
                }

                // Build the category object based from the coursecat object.
                $mycategory = new stdClass();
                $mycategory->id = $coursecat->id;
                $mycategory->name = $coursecat->name;
                $mycategory->visible = $coursecat->visible;

                // Add this category to the nav tree.
                $this->add_category($mycategory, $parent, self::TYPE_MY_CATEGORY);
            }
        }

        // Go through each course now and add it to the nav block, and the flatnav if applicable.
        foreach ($courses as $course) {
            $node = $this->add_course($course, false, self::COURSE_MY);
            if ($node) {
                $node->showinflatnavigation = false;
                // Check if we should also add this to the flat nav as well.
                if (isset($flatnavcourses[$course->id])) {
                    $node->showinflatnavigation = true;
                }
            }
        }

        // Go through each course in the flatnav now.
        foreach ($flatnavcourses as $course) {
            // Check if we haven't already added it.
            if (!isset($courses[$course->id])) {
                // Ok, add it to the flatnav only.
                $node = $this->add_course($course, false, self::COURSE_MY);
                $node->display = false;
                $node->showinflatnavigation = true;
            }
        }

        $showmorelinkinnav = $numtotalcourses > $numshowncourses;
        $showmorelinkinflatnav = $numtotalflatnavcourses > $numshownflatnavcourses;
        // Show a link to the course page if there are more courses the user is enrolled in.
        if ($showmorelinkinnav || $showmorelinkinflatnav) {
            // Adding hash to URL so the link is not highlighted in the navigation when clicked.
            $url = new moodle_url('/my/courses.php');
            $parent = $this->rootnodes['mycourses'];
            $coursenode = $parent->add(get_string('morenavigationlinks'), $url, self::TYPE_CUSTOM, null, self::COURSE_INDEX_PAGE);

            if ($showmorelinkinnav) {
                $coursenode->display = true;
            }

            if ($showmorelinkinflatnav) {
                $coursenode->showinflatnavigation = true;
            }
        }
    }
}