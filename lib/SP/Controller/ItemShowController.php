<?php
/**
 * sysPass
 *
 * @author nuxsmin 
 * @link https://syspass.org
 * @copyright 2012-2018, Rubén Domínguez nuxsmin@$syspass.org
 *
 * This file is part of sysPass.
 *
 * sysPass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 *  along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SP\Controller;

defined('APP_ROOT') || die();

use SP\Account\Account;
use SP\Account\AccountAcl;
use SP\Account\AccountHistory;
use SP\Core\Acl\ActionsInterface;
use SP\Core\Crypt\Crypt;
use SP\Core\Crypt\Session as CryptSession;
use SP\Core\Exceptions\ItemException;
use SP\Core\Plugin\PluginUtil;
use SP\Core\SessionFactory;
use SP\Core\SessionUtil;
use SP\DataModel\AccountExtData;
use SP\DataModel\AuthTokenData;
use SP\DataModel\CategoryData;
use SP\DataModel\ClientData;
use SP\DataModel\CustomFieldData;
use SP\DataModel\CustomFieldDefinitionData;
use SP\DataModel\ProfileData;
use SP\DataModel\TagData;
use SP\DataModel\UserData;
use SP\DataModel\UserGroupData;
use SP\Http\Request;
use SP\Log\Email;
use SP\Log\Log;
use SP\Mgmt\ApiTokens\ApiToken;
use SP\Mgmt\ApiTokens\ApiTokensUtil;
use SP\Mgmt\Categories\Category;
use SP\Mgmt\Customers\Customer;
use SP\Mgmt\CustomFields\CustomField;
use SP\Mgmt\CustomFields\CustomFieldDef;
use SP\Mgmt\CustomFields\CustomFieldTypes;
use SP\Mgmt\Files\FileUtil;
use SP\Mgmt\Groups\Group;
use SP\Mgmt\Groups\GroupUsers;
use SP\Mgmt\Plugins\Plugin;
use SP\Mgmt\Profiles\Profile;
use SP\Mgmt\Profiles\ProfileUtil;
use SP\Mgmt\PublicLinks\PublicLink;
use SP\Mgmt\Tags\Tag;
use SP\Mgmt\Users\User;
use SP\Mgmt\Users\UserPass;
use SP\Mgmt\Users\UserUtil;
use SP\Modules\Web\Controllers\ControllerBase;
use SP\Mvc\View\Template;
use SP\Util\ImageUtil;
use SP\Util\Json;

/**
 * Class AccItemMgmt
 *
 * @package SP\Controller
 */
class ItemShowController extends ControllerBase implements ActionsInterface, ItemControllerInterface
{
    use RequestControllerTrait;

    /**
     * Máximo numero de acciones antes de agrupar
     */
    const MAX_NUM_ACTIONS = 3;
    /**
     * @var int
     */
    private $module = 0;

    /**
     * Constructor
     *
     * @param $template Template con instancia de plantilla
     * @throws \SP\Core\Exceptions\SPException
     */
    public function __construct(Template $template = null)
    {
        parent::__construct($template);

        $this->init();

        $this->view->assign('isDemo', $this->configData->isDemoEnabled());
        $this->view->assign('sk', SessionUtil::getSessionKey(true));
        $this->view->assign('itemId', $this->itemId);
        $this->view->assign('activeTab', $this->activeTab);
        $this->view->assign('actionId', $this->actionId);
        $this->view->assign('isView', false);
        $this->view->assign('showViewCustomPass', true);
        $this->view->assign('readonly', '');
    }

    /**
     * Realizar la acción solicitada en la la petición HTTP
     *
     * @param mixed $type Tipo de acción
     * @throws \SP\Core\Exceptions\SPException
     */
    public function doAction($type = null)
    {
        try {
            switch ($this->actionId) {
                case self::USER_VIEW:
                    $this->view->assign('header', __('Ver Usuario'));
                    $this->view->assign('isView', true);
                    $this->getUser();
                    break;
                case self::USER_EDIT:
                    $this->view->assign('header', __('Editar Usuario'));
                    $this->getUser();
                    break;
                case self::USER_EDIT_PASS:
                    $this->view->assign('header', __('Cambio de Clave'));
                    $this->getUserPass();
                    break;
                case self::USER_CREATE:
                    $this->view->assign('header', __('Nuevo Usuario'));
                    $this->getUser();
                    break;
                case self::GROUP_VIEW:
                    $this->view->assign('header', __('Ver Grupo'));
                    $this->view->assign('isView', true);
                    $this->getGroup();
                    break;
                case self::GROUP_EDIT:
                    $this->view->assign('header', __('Editar Grupo'));
                    $this->getGroup();
                    break;
                case self::GROUP_CREATE:
                    $this->view->assign('header', __('Nuevo Grupo'));
                    $this->getGroup();
                    break;
                case self::PROFILE_VIEW:
                    $this->view->assign('header', __('Ver Perfil'));
                    $this->view->assign('isView', true);
                    $this->getProfile();
                    break;
                case self::PROFILE_EDIT:
                    $this->view->assign('header', __('Editar Perfil'));
                    $this->getProfile();
                    break;
                case self::PROFILE_CREATE:
                    $this->view->assign('header', __('Nuevo Perfil'));
                    $this->getProfile();
                    break;
                case self::CLIENT_VIEW:
                    $this->view->assign('header', __('Ver Cliente'));
                    $this->view->assign('isView', true);
                    $this->getCustomer();
                    break;
                case self::CLIENT_EDIT:
                    $this->view->assign('header', __('Editar Cliente'));
                    $this->getCustomer();
                    break;
                case self::CLIENT_CREATE:
                    $this->view->assign('header', __('Nuevo Cliente'));
                    $this->getCustomer();
                    break;
                case self::CATEGORY_VIEW:
                    $this->view->assign('header', __('Ver Categoría'));
                    $this->view->assign('isView', true);
                    $this->getCategory();
                    break;
                case self::CATEGORY_EDIT:
                    $this->view->assign('header', __('Editar Categoría'));
                    $this->getCategory();
                    break;
                case self::CATEGORY_CREATE:
                    $this->view->assign('header', __('Nueva Categoría'));
                    $this->getCategory();
                    break;
                case self::APITOKEN_VIEW:
                    $this->view->assign('header', __('Ver Autorización'));
                    $this->view->assign('isView', true);
                    $this->getToken();
                    break;
                case self::APITOKEN_CREATE:
                    $this->view->assign('header', __('Nueva Autorización'));
                    $this->getToken();
                    break;
                case self::APITOKEN_EDIT:
                    $this->view->assign('header', __('Editar Autorización'));
                    $this->getToken();
                    break;
                case self::CUSTOMFIELD_CREATE:
                    $this->view->assign('header', __('Nuevo Campo'));
                    $this->getCustomField();
                    break;
                case self::CUSTOMFIELD_EDIT:
                    $this->view->assign('header', __('Editar Campo'));
                    $this->getCustomField();
                    break;
                case self::PUBLICLINK_VIEW:
                    $this->view->assign('header', __('Ver Enlace Público'));
                    $this->view->assign('isView', true);
                    $this->getPublicLink();
                    break;
                case self::TAG_CREATE:
                    $this->view->assign('header', __('Nueva Etiqueta'));
                    $this->getTag();
                    break;
                case self::TAG_EDIT:
                    $this->view->assign('header', __('Editar Etiqueta'));
                    $this->getTag();
                    break;
                case self::ACCOUNT_VIEW_PASS:
                    $this->view->assign('header', __('Clave de Cuenta'));
                    $this->getAccountPass();
                    break;
                case self::PLUGIN_VIEW:
                    $this->view->assign('header', __('Detalles de Plugin'));
                    $this->view->assign('isView', true);
                    $this->getPlugin();
                    break;
                default:
                    $this->invalidAction();
            }

            if (count($this->JsonResponse->getData()) === 0) {
                $this->JsonResponse->setData(['html' => $this->render()]);
            }
        } catch (\Exception $e) {
            $this->JsonResponse->setDescription($e->getMessage());
        }

        $this->JsonResponse->setCsrf($this->view->sk);

        Json::returnJson($this->JsonResponse);
    }

    /**
     * Obtener los datos para la ficha de usuario
     *
     * @throws \SP\Core\Exceptions\SPException
     * @throws \SP\Core\Exceptions\InvalidClassException
     * @throws \SP\Core\Exceptions\FileNotFoundException
     */
    protected function getUser()
    {
        $this->module = self::USER;
        $this->view->addTemplate('users');

        $this->view->assign('user', $this->itemId ? User::getItem()->getById($this->itemId) : new UserData());
        $this->view->assign('isDisabled', $this->view->actionId === self::USER_VIEW ? 'disabled' : '');
        $this->view->assign('isReadonly', $this->view->isDisabled ? 'readonly' : '');
        $this->view->assign('isUseSSO', $this->configData->isAuthBasicAutoLoginEnabled());
        $this->view->assign('groups', Group::getItem()->getItemsForSelect());
        $this->view->assign('profiles', Profile::getItem()->getItemsForSelect());

        $this->getCustomFieldsForItem();

        $this->JsonResponse->setStatus(0);
    }

    /**
     * Obtener la lista de campos personalizados y sus valores
     *
     * @throws \SP\Core\Exceptions\InvalidClassException
     */
    protected function getCustomFieldsForItem()
    {
        $this->view->assign('customFields', CustomField::getItem(new CustomFieldData($this->module))->getById($this->itemId));
    }

    /**
     * Inicializar la vista de cambio de clave de usuario
     *
     * @throws \SP\Core\Exceptions\SPException
     */
    protected function getUserPass()
    {
        $this->module = self::USER;
        $this->setAction(self::USER_EDIT_PASS);

        // Comprobar si el usuario a modificar es distinto al de la sesión
        if ($this->itemId !== SessionFactory::getUserData()->getId() && !$this->checkAccess()) {
            return;
        }

        $this->view->assign('user', User::getItem()->getById($this->itemId));
        $this->view->addTemplate('userspass');

        $this->JsonResponse->setStatus(0);
    }

    /**
     * Obtener los datos para la ficha de grupo
     *
     * @throws \SP\Core\Exceptions\InvalidClassException
     * @throws \SP\Core\Exceptions\FileNotFoundException
     */
    protected function getGroup()
    {
        $this->module = self::GROUP;
        $this->view->addTemplate('groups');

        $this->view->assign('group', $this->itemId ? Group::getItem()->getById($this->itemId) : new UserGroupData());
        $this->view->assign('users', User::getItem()->getItemsForSelect());
        $this->view->assign('groupUsers', GroupUsers::getItem()->getById($this->itemId));

        $this->getCustomFieldsForItem();

        $this->JsonResponse->setStatus(0);
    }

    /**
     * Obtener los datos para la ficha de perfil
     *
     * @throws \SP\Core\Exceptions\InvalidClassException
     * @throws \SP\Core\Exceptions\FileNotFoundException
     */
    protected function getProfile()
    {
        $this->module = self::PROFILE;
        $this->view->addTemplate('profiles');

        $Profile = $this->itemId ? Profile::getItem()->getById($this->itemId) : new ProfileData();

        $this->view->assign('profile', $Profile);
        $this->view->assign('isDisabled', ($this->view->actionId === self::PROFILE_VIEW) ? 'disabled' : '');
        $this->view->assign('isReadonly', $this->view->isDisabled ? 'readonly' : '');

        if ($this->view->isView === true) {
            $this->view->assign('usedBy', ProfileUtil::getProfileInUsersName($this->itemId));
        }

        $this->JsonResponse->setStatus(0);
    }

    /**
     * Obtener los datos para la ficha de cliente
     *
     * @throws \SP\Core\Exceptions\InvalidClassException
     * @throws \SP\Core\Exceptions\FileNotFoundException
     */
    protected function getCustomer()
    {
        $this->module = self::CLIENT;
        $this->view->addTemplate('customers');

        $this->view->assign('customer', $this->itemId ? Customer::getItem()->getById($this->itemId) : new ClientData());
        $this->getCustomFieldsForItem();

        $this->JsonResponse->setStatus(0);
    }

    /**
     * Obtener los datos para la ficha de categoría
     *
     * @throws \SP\Core\Exceptions\InvalidClassException
     * @throws \SP\Core\Exceptions\FileNotFoundException
     */
    protected function getCategory()
    {
        $this->module = self::CATEGORY;
        $this->view->addTemplate('categories');

        $this->view->assign('category', $this->itemId ? Category::getItem()->getById($this->itemId) : new CategoryData());
        $this->getCustomFieldsForItem();

        $this->JsonResponse->setStatus(0);
    }

    /**
     * Obtener los datos para la ficha de tokens de API
     *
     * @throws \SP\Core\Exceptions\InvalidClassException
     * @throws \SP\Core\Exceptions\FileNotFoundException
     * @throws \SP\Core\Exceptions\SPException
     * @throws \phpmailer\phpmailerException
     */
    protected function getToken()
    {
        $this->module = self::APITOKEN;
        $this->view->addTemplate('tokens');

        $ApiTokenData = $this->itemId ? ApiToken::getItem()->getById($this->itemId) : new AuthTokenData();

        $this->view->assign('users', User::getItem()->getItemsForSelect());
        $this->view->assign('actions', ApiTokensUtil::getTokenActions());
        $this->view->assign('authTokenData', $ApiTokenData);
        $this->view->assign('isDisabled', ($this->view->actionId === self::APITOKEN_VIEW) ? 'disabled' : '');
        $this->view->assign('isReadonly', $this->view->isDisabled ? 'readonly' : '');

        if ($this->view->isView === true) {
            $Log = Log::newLog(__('Autorizaciones', false));
            $LogMessage = $Log->getLogMessage();
            $LogMessage->addDescription(__('Token de autorización visualizado'));
            $LogMessage->addDetails(__('Usuario'), UserUtil::getUserLoginById($ApiTokenData->authtoken_userId));
            $Log->writeLog();

            Email::sendEmail($LogMessage);
        }

        $this->JsonResponse->setStatus(0);
    }

    /**
     * Obtener los datos para la ficha de campo personalizado
     *
     * @throws \SP\Core\Exceptions\InvalidClassException
     * @throws \SP\Core\Exceptions\FileNotFoundException
     * @throws \SP\Core\Exceptions\SPException
     */
    protected function getCustomField()
    {
        $this->module = self::CUSTOMFIELD;
        $this->view->addTemplate('customfields');

        $customField = $this->itemId ? CustomFieldDef::getItem()->getById($this->itemId) : new CustomFieldDefinitionData();

        $this->view->assign('field', $customField);
        $this->view->assign('types', CustomFieldTypes::getFieldsTypes());
        $this->view->assign('modules', CustomFieldTypes::getFieldsModules());

        $this->JsonResponse->setStatus(0);
    }

    /**
     * Obtener los datos para la ficha de enlace público
     *
     * @throws \SP\Core\Exceptions\SPException
     * @throws \SP\Core\Exceptions\InvalidClassException
     * @throws \SP\Core\Exceptions\FileNotFoundException
     */
    protected function getPublicLink()
    {
        $this->module = self::PUBLICLINK;
        $this->view->addTemplate('publiclinks');

        $PublicLink = PublicLink::getItem();

        $this->view->assign('link', $PublicLink->getItemForList($PublicLink->getById($this->itemId)));

        $this->JsonResponse->setStatus(0);
    }

    /**
     * Obtener los datos para la ficha de categoría
     *
     * @throws \SP\Core\Exceptions\SPException
     * @throws \SP\Core\Exceptions\InvalidClassException
     * @throws \SP\Core\Exceptions\FileNotFoundException
     */
    protected function getTag()
    {
        $this->module = self::TAG;
        $this->view->addTemplate('tags');

        $this->view->assign('tag', $this->itemId ? Tag::getItem()->getById($this->itemId) : new TagData());

        $this->JsonResponse->setStatus(0);
    }

    /**
     * Mostrar la clave de una cuenta
     *
     * @throws ItemException
     * @throws \SP\Core\Exceptions\InvalidClassException
     * @throws \SP\Core\Exceptions\SPException
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     * @throws \SP\Core\Exceptions\FileNotFoundException
     * @throws \Defuse\Crypto\Exception\CryptoException
     * @throws \Defuse\Crypto\Exception\BadFormatException
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     */
    public function getAccountPass()
    {
        $this->setAction(self::ACCOUNT_VIEW_PASS);

        $isHistory = Request::analyze('isHistory', false);
        $isFull = Request::analyze('isFull', false);

        $AccountData = new AccountExtData();

        if (!$isHistory) {
            $AccountData->setId($this->itemId);
            $Account = new Account($AccountData);
        } else {
            $Account = new AccountHistory($AccountData);
            $Account->setId($this->itemId);
        }

        $Account->getAccountPassData();

        if ($isHistory && !$Account->checkAccountMPass()) {
            throw new ItemException(__('La clave maestra no coincide', false));
        }

        $AccountAcl = new AccountAcl(ActionsInterface::ACCOUNT_VIEW_PASS);
        $Acl = $AccountAcl->getAcl();

        if (!$Acl->isShowViewPass()) {
            throw new ItemException(__('No tiene permisos para acceder a esta cuenta', false));
        }

        if (!UserPass::checkUserUpdateMPass(SessionFactory::getUserData()->getId())) {
            throw new ItemException(__('Clave maestra actualizada') . '<br>' . __('Reinicie la sesión para cambiarla'));
        }

        $key = CryptSession::getSessionKey();
        $securedKey = Crypt::unlockSecuredKey($AccountData->getKey(), $key);
        $accountClearPass = Crypt::decrypt($AccountData->getPass(), $securedKey, $key);

        if (!$isHistory) {
            $Account->incrementDecryptCounter();

            $Log = new Log();
            $LogMessage = $Log->getLogMessage();
            $LogMessage->setAction(__('Ver Clave', false));
            $LogMessage->addDetails(__('ID', false), $this->itemId);
            $LogMessage->addDetails(__('Cuenta', false), $AccountData->getClientName() . ' / ' . $AccountData->getName());
            $Log->writeLog();
        }

        $useImage = $this->configData->isAccountPassToImage();

        if (!$useImage) {
            $pass = $isFull ? htmlentities(trim($accountClearPass)) : trim($accountClearPass);
        } else {
            $pass = ImageUtil::convertText($accountClearPass);
        }

        $this->JsonResponse->setStatus(0);

        if ($isFull) {
            $this->view->addTemplate('viewpass', 'account');

            $this->view->assign('login', $AccountData->getLogin());
            $this->view->assign('pass', $pass);
            $this->view->assign('isImage', $useImage);
            $this->view->assign('isLinked', Request::analyze('isLinked', 0));

            return;
        }

        $data = [
            'acclogin' => $AccountData->getLogin(),
            'accpass' => $pass,
            'useimage' => $useImage
        ];

        $this->JsonResponse->setData($data);
    }

    /**
     * Obtener los datos para la vista de plugins
     *
     * @throws \SP\Core\Exceptions\InvalidClassException
     * @throws \SP\Core\Exceptions\SPException
     * @throws \SP\Core\Exceptions\FileNotFoundException
     */
    protected function getPlugin()
    {
        $this->module = self::PLUGIN;
        $this->view->addTemplate('plugins');

        $Plugin = Plugin::getItem()->getById($this->itemId);

        $this->view->assign('isReadonly', $this->view->isView ? 'readonly' : '');
        $this->view->assign('plugin', $Plugin);
        $this->view->assign('pluginInfo', PluginUtil::getPluginInfo($Plugin->getName()));

        $this->JsonResponse->setStatus(0);
    }

    /**
     * Obtener los datos para la vista de archivos de una cuenta
     *
     * @throws \SP\Core\Exceptions\FileNotFoundException
     */
    protected function getAccountFiles()
    {
        $this->setAction(self::ACCOUNT_FILE);

        $this->view->assign('accountId', Request::analyze('id', 0));
        $this->view->assign('deleteEnabled', Request::analyze('del', 0));
        $this->view->assign('files', FileUtil::getAccountFiles($this->view->accountId));

        if (!is_array($this->view->templates) || count($this->view->templates) === 0) {
            return;
        }

        $this->view->addTemplate('files');

        $this->JsonResponse->setStatus(0);
    }
}