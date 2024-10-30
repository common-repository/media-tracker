<?php

namespace Media_Tracker;

/**
 * The admin class
 */
class Admin {

    /**
     * Initialize the class
     */
    function __construct() {
        new Admin\Menu();
        new Admin\Media_Usage();
        new Admin\Duplicate_Images();
    }
}
