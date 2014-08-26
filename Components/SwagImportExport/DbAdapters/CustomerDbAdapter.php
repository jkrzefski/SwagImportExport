<?php

namespace Shopware\Components\SwagImportExport\DbAdapters;

use Shopware\Models\Customer\Customer;
use Shopware\Components\SwagImportExport\Utils\DataHelper;
use Shopware\Components\SwagImportExport\Utils\DbAdapterHelper;

class CustomerDbAdapter implements DataDbAdapter
{

    /**
     * Shopware\Components\Model\ModelManager
     */
    protected $manager;
    protected $repository;
    protected $billingMap;

    public function getDefaultColumns()
    {
        $default = array();

        $default = array_merge($default, $this->getCustomerColumns());
        
        $default = array_merge($default, $this->getBillingColumns());

        $default = array_merge($default, $this->getShippingColumns());
        
        return $default;
    }

    public function getCustomerColumns()
    {
        $columns = array(
            'customer.id as id',
            'customer.hashPassword as password',
            'unhashedPassword',
            'customer.encoderName as encoder',
            'customer.email as email',
            'customer.active as active',
            'customer.accountMode as accountMode',
            'customer.paymentId as paymentID',
            'customer.firstLogin as firstLogin',
            'customer.lastLogin as lastLogin',
            'customer.sessionId as sessionId',
            'customer.newsletter as newsletter',
            'customer.validation as validation',
            'customer.affiliate as affiliate',
            'customer.groupKey as customergroup',
            'customer.paymentPreset as paymentPreset',
            'customer.languageId as language',
            'customer.shopId as subshopID',
            'customer.referer as referer',
            'customer.priceGroupId as priceGroupId',
            'customer.internalComment as internalComment',
            'customer.failedLogins as failedLogins',
            'customer.lockedUntil as lockedUntil',
        );
        
        // Attributes
        $stmt = Shopware()->Db()->query('SELECT * FROM s_user_attributes LIMIT 1');
        $attributes = $stmt->fetch();

        $attributesSelect = '';
        if ($attributes) {
            unset($attributes['id']);
            unset($attributes['userID']);
            $attributes = array_keys($attributes);

            $prefix = 'attribute';
            $attributesSelect = array();
            foreach ($attributes as $attribute) {
                //underscore to camel case
                //exmaple: underscore_to_camel_case -> underscoreToCamelCase
                $catAttr = preg_replace("/\_(.)/e", "strtoupper('\\1')", $attribute);

                $attributesSelect[] = sprintf('%s.%s as attrCustomer%s', $prefix, $catAttr, ucwords($catAttr));
            }
        }
        
        if ($attributesSelect && !empty($attributesSelect)) {
            $columns = array_merge($columns, $attributesSelect);
        }
        
        return $columns;
    }

    public function getBillingColumns()
    {
        $columns = array(
            'billing.company as billingCompany',
            'billing.department as billingDepartment',
            'billing.salutation as billingSalutation',
            'billing.number as customerNumber',
            'billing.firstName as billingFirstname',
            'billing.lastName as billingLastname',
            'billing.street as billingStreet',
            'billing.streetNumber as billingStreetnumber',
            'billing.zipCode as billingZipcode',
            'billing.city as billingCity',
            'billing.phone as billingPhone',
            'billing.fax as billingFax',
            'billing.countryId as billingCountryID',
            'billing.stateId as billingStateID',
            'billing.vatId as ustid',
            'billing.birthday as birthday',
        );
        
        // Attributes
        $stmt = Shopware()->Db()->query('SELECT * FROM s_user_billingaddress_attributes LIMIT 1');
        $attributes = $stmt->fetch();

        $attributesSelect = '';
        if ($attributes) {
            unset($attributes['id']);
            unset($attributes['billingID']);
            $attributes = array_keys($attributes);

            $prefix = 'billingAttribute';
            $attributesSelect = array();
            foreach ($attributes as $attribute) {
                //underscore to camel case
                //exmaple: underscore_to_camel_case -> underscoreToCamelCase
                $catAttr = preg_replace("/\_(.)/e", "strtoupper('\\1')", $attribute);

                $attributesSelect[] = sprintf('%s.%s as attrBilling%s', $prefix, $catAttr, ucwords($catAttr));
            }
        }
        
        if ($attributesSelect && !empty($attributesSelect)) {
            $columns = array_merge($columns, $attributesSelect);
        }

        return $columns;
    }

    public function getShippingColumns()
    {
        $columns = array(
            'shipping.company as shippingCompany',
            'shipping.department as shippingDepartment',
            'shipping.salutation as shippingSalutation',
            'shipping.firstName as shippingFirstname',
            'shipping.lastName as shippingLastname',
            'shipping.street as shippingStreet',
            'shipping.streetNumber as shippingStreetnumber',
            'shipping.zipCode as shippingZipcode',
            'shipping.city as shippingCity',
            'shipping.countryId as shippingCountryID',
            'shipping.stateId as shippingStateID',
        );
        
        // Attributes
        $stmt = Shopware()->Db()->query('SELECT * FROM s_user_shippingaddress_attributes LIMIT 1');
        $attributes = $stmt->fetch();

        $attributesSelect = '';
        if ($attributes) {
            unset($attributes['id']);
            unset($attributes['shippingID']);
            $attributes = array_keys($attributes);

            $prefix = 'shippingAttribute';
            $attributesSelect = array();
            foreach ($attributes as $attribute) {
                //underscore to camel case
                //exmaple: underscore_to_camel_case -> underscoreToCamelCase
                $catAttr = preg_replace("/\_(.)/e", "strtoupper('\\1')", $attribute);

                $attributesSelect[] = sprintf('%s.%s as attrShipping%s', $prefix, $catAttr, ucwords($catAttr));
            }
        }

        if ($attributesSelect && !empty($attributesSelect)) {
            $columns = array_merge($columns, $attributesSelect);
        }

        return $columns;
    }

    public function read($ids, $columns)
    {
        $manager = $this->getManager();

        $builder = $manager->createQueryBuilder();
        
        foreach ($columns as $key => $value) {
            if ($value == 'unhashedPassword') {
                unset($columns[$key]);
            }
        }
                
        $builder->select($columns)
                ->from('\Shopware\Models\Customer\Customer', 'customer')
                ->join('customer.billing', 'billing')
                ->leftJoin('customer.shipping', 'shipping')
                ->leftJoin('customer.orders', 'orders', 'WITH', 'orders.status <> -1 AND orders.status <> 4')
                ->leftJoin('billing.attribute', 'billingAttribute')
                ->leftJoin('shipping.attribute', 'shippingAttribute')
                ->leftJoin('customer.attribute', 'attribute')
                ->groupBy('customer.id')
                ->where('customer.id IN (:ids)')
                ->setParameter('ids', $ids);

        $query = $builder->getQuery();

        $query->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $paginator = $manager->createPaginator($query);

        $customers = $paginator->getIterator()->getArrayCopy();
        
        $result['default'] = DbAdapterHelper::decodeHtmlEntities($customers);
        
        return $result;
    }

    public function readRecordIds($start, $limit, $filter)
    {
        $manager = $this->getManager();

        $builder = $manager->createQueryBuilder();

        $builder->select('customer.id')
                ->from('\Shopware\Models\Customer\Customer', 'customer');

        $builder->setFirstResult($start)
                ->setMaxResults($limit);

        if (!empty($filter)) {
            $builder->addFilter($filter);
        }

        $records = $builder->getQuery()->getResult();

        $result = array();
        if ($records) {
            foreach ($records as $value) {
                $result[] = $value['id'];
            }
        }
        
        return $result;
    }

    public function write($records)
    {
        $manager = $this->getManager();
        $passwordManager = Shopware()->PasswordEncoder();
        $db = Shopware()->Db();
        
        foreach ($records['default'] as $record) {

            if (!$record['email']) {
                throw new \Exception("User email is required field.");
                //todo: log this result
                continue;
            }

            $customer = $this->getRepository()->findOneBy(array('email' => $record['email']));
            
            if (isset($record['unhashedPassword']) && $record['unhashedPassword'] 
                && (!isset($record['password']) || !$record['password'])) {
                
                if (!isset($record['encoder']) || !$record['encoder']) {
                    $record['encoder'] = $passwordManager->getDefaultPasswordEncoderName();
                }

                $encoder = $passwordManager->getEncoderByName($record['encoder']);

                $record['password'] = $encoder->encodePassword($record['unhashedPassword']);

                unset($record['unhashedPassword']);
            }
            
            if (!$customer) {
                $customer = new Customer();
            }

            if (isset($record['password']) && !$record['password']) {
                throw new \Exception('Password must be provided');
            }
            
            if (isset($record['password']) && (!isset($record['encoder']) || !$record['encoder'])) {
                throw new \Exception('Password encoder must be provided');
            }
           
            $customerData = $this->prepareCustomer($record);

            $customerData['billing'] = $this->prepareBilling($record);

            $customerData['shipping'] = $this->prepareShipping($record);
            
            $customer->fromArray($customerData);

            $violations = $this->getManager()->validate($customer);

            if ($violations->count() > 0) {
                throw new \Exception($violations);
            }
            
            $manager->persist($customer);
            $manager->flush();
            
            if (isset($customerData['encoderName']) && $customerData['encoderName']) {
                $customerId = $customer->getId();
                
                $data['encoder'] = lcfirst($customerData['encoderName']);
                $whereUser = array('id=' . $customerId);
                $db->update('s_user', $data, $whereUser);
            }
        }
    }

    protected function prepareCustomer(&$record)
    {
        if ($this->customerMap === null) {
            $columns = $this->getCustomerColumns();

            foreach ($columns as $column) {

                $map = DataHelper::generateMappingFromColumns($column);
                $this->customerMap[$map[0]] = $map[1];
            }
        }
        
        $customerData = array();
        foreach ($record as $key => $value) {
            if (isset($this->customerMap[$key])) {
                $customerData[$this->customerMap[$key]] = $value;
                unset($record[$key]);
            }
        }
        
        $customerData['rawPassword'] = $customerData['hashPassword'];
        unset($record['hashPassword']);

        return $customerData;
    }

    protected function prepareBilling(&$record)
    {
        if ($this->billingMap === null) {
            $columns = $this->getBillingColumns();

            foreach ($columns as $column) {

                $map = DataHelper::generateMappingFromColumns($column);
                $this->billingMap[$map[0]] = $map[1];
            }
        }

        $billingData = array();
        foreach ($record as $key => $value) {
            if (isset($this->billingMap[$key])) {
                $billingData[$this->billingMap[$key]] = $value;
                unset($record[$key]);
            }
        }

        return $billingData;
    }

    protected function prepareShipping(&$record)
    {
        if ($this->shippingMap === null) {
            $columns = $this->getShippingColumns();

            foreach ($columns as $column) {

                $map = DataHelper::generateMappingFromColumns($column);
                $this->shippingMap[$map[0]] = $map[1];
            }
        }

        $shippingData = array();
        foreach ($record as $key => $value) {
            if (isset($this->shippingMap[$key])) {
                $shippingData[$this->shippingMap[$key]] = $value;
                unset($record[$key]);
            }
        }

        return $shippingData;
    }
    
    /**
     * @return array
     */
    public function getSections()
    {
        return array(
            array('id' => 'default', 'name' => 'default ')
        );
    }
    
    /**
     * @param string $section
     * @return mix
     */
    public function getColumns($section)
    {
        $method = 'get' . ucfirst($section) . 'Columns';
        
        if (method_exists($this, $method)) {
            return $this->{$method}();
        }

        return false;
    }

    /**
     * Returns category repository
     * 
     * @return Shopware\Models\Customer\Customer
     */
    public function getRepository()
    {
        if ($this->repository === null) {
            $this->repository = $this->getManager()->getRepository('Shopware\Models\Customer\Customer');
        }
        return $this->repository;
    }

    /**
     * Returns entity manager
     * 
     * @return Shopware\Components\Model\ModelManager
     */
    public function getManager()
    {
        if ($this->manager === null) {
            $this->manager = Shopware()->Models();
        }

        return $this->manager;
    }

}