<?php

namespace FFCMS\Mappers;

use FFCMS\{Traits, Models};

/**
 * Users Mapper Class.
 *
 * @author Vijay Mahrra <vijay@yoyo.org>
 * @copyright (c) Copyright 2016 Vijay Mahrra
 * @license GPLv3 (http://www.gnu.org/licenses/gpl-3.0.html)
 *
 * @property int    $id
 * @property string $uuid
 * @property string $password
 * @property string $email
 * @property string $firstname
 * @property string $lastname
 * @property string $scopes
 * @property string $status
 * @property string $password_question
 * @property string $password_answer
 * @property string $created
 * @property string $login_count
 * @property string $login_last
 */
class Users extends Mapper
{
    use Traits\UrlHelper;

    /**
     * Fields and their visibility to clients, boolean or string of visible field name
     *
     * @var array $fieldsVisible
     */
    public $fieldsVisible = [
        'uuid'              => 'id',
        'password'          => false,
        'scopes'            => false,
        'login_count'       => false,
    ];

    /**
     * Fields that are editable to clients, boolean or string of visible field name
     *
     * @var array $fieldsEditable
     */
    public $fieldsEditable = [
        'email',
        'firstname',
        'lastname',
        'password_question',
        'password_answer',
    ];

    /**
     * Filter rules for fields
     *
     * @var array $filterRules
     * @link https://github.com/Wixel/GUMP
     */
    public $filterRules = [
        'uuid'              => 'trim|sanitize_string|lower',
        'password'          => 'trim|sanitize_string',
        'email'             => 'trim|sanitize_string|sanitize_email|lower',
        'firstname'         => 'trim|sanitize_string',
        'lastname'          => 'trim|sanitize_string',
        'scopes'            => 'trim|sanitize_string|lower',
        'status'            => 'trim|sanitize_string|lower',
        'password_question' => 'trim|sanitize_string',
        'password_answer'   => 'trim|sanitize_string',
        'created'           => 'trim|sanitize_string',
        'login_count'       => 'sanitize_numbers|whole_number',
        'login_last'        => 'trim|sanitize_string',
    ];

    /**
     * Validation rules for fields
     *
     * @var array $validationRules
     * @link https://github.com/Wixel/GUMP
     */
    public $validationRules = [
        'uuid'              => 'alpha_dash',
        'email'             => 'valid_email',
        'firstname'         => 'valid_name',
    ];

    /**
     * Create if needed, and return the path to the user profile image
     *
     * @param string $uuid the user uuid
     * @return string $path to the profile image
     */
    public function profileImageFilePath()
    {
        $f3  = \Base::instance();
        $dir = $f3->get('assets.dir') . '/img/users/' . $this->uuid;
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir . '/' . 'profile.png';
    }

    /**
     * Return the URL path to the image if exists or false
     *
     * @param string $uuid the user uuid
     * @return bool true if the profile image exists
     */
    public function profileImageExists()
    {
        return file_exists($this->profileImageFilePath());
    }

    /**
     * Return the URL path to the image if exists or false
     *
     * @param string $uuid the user uuid
     * @return false|string return the url path or false if not exists
     */
    public function profileImageUrlPath()
    {
        $url = $this->profileImageExists() ? '/assets/img/users/' . $this->uuid . '/profile.png' : false;
        if (empty($url)) {
            return false;
        }
        return $url . '?' . filesize($this->profileImageFilePath());
    }

    /**
     * Create profile image from given file
     *
     * @param string $file path to file
     * @return boolean if the file was written and and asset record created
     */
    public function profileImageCreate($file)
    {
        if (!file_exists($file)) {
            throw new Exceptions\Exception('Profile image creation file does not exist.');
        }
        $f3 = \Base::instance();

        // read exif metadata
        $reader = \PHPExif\Reader\Reader::factory(\PHPExif\Reader\Reader::TYPE_NATIVE);
        $exif = $reader->read($file);
        $metadata = $exif->getData();
        unset($exif);

        // convert image to png
        $img = new \Image($file);
        $resize = $f3->get('assets.images.profile');
        $img->resize($resize['width'], $resize['height']);

        // convert to .png, create new profile image file
        $profileImagePath = $this->profileImageFilePath();
        if (!$f3->write($profileImagePath, $img->dump('png', 9))) {
            return false;
        }

        // create asset table entry
        $asset = new Assets;

        // load pre existing asset
        $asset->load(['users_uuid = ? AND ' . $this->db->quoteKey('key') . ' = ?', $this->uuid, 'profile']);

        // set values
        $asset->users_uuid = $this->uuid;
        $asset->filename = $profileImagePath;
        $asset->name = $this->firstname . ' ' . $this->lastname;
        $asset->description = $this->firstname . ' ' . $this->lastname . ' Profile Image';
        $asset->size = filesize($profileImagePath);
        $asset->url = $this->url($this->profileImageUrlPath());
        $asset->type = 'image/png';
        $asset->key = 'profile_image';
        $asset->groups = 'users';
        $asset->categories = 'profile';
        $asset->tags = 'users,profile';
        $asset->metadata = json_encode($metadata, JSON_PRETTY_PRINT);

        return $asset->save();
    }
}
