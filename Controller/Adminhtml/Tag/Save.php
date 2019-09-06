<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Lof\ProductTags\Controller\Adminhtml\Tag;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Backend\App\Action\Context;
use Lof\ProductTags\Api\TagRepositoryInterface;
use Lof\ProductTags\Model\TagFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\Filter\Date;

/**
 * Save CMS Tag action.
 */
class Save extends \Lof\ProductTags\Controller\Adminhtml\Tag implements HttpPostActionInterface
{
    protected $dataPersistor;
    private $TagFactory = null;
    private $tagRepository = null;
    public function __construct(
        \Magento\Framework\Stdlib\DateTime\Filter\Date  $date = null,
        Context $context,
        DataPersistorInterface $dataPersistor,
        TagFactory $TagFactory,
        TagRepositoryInterface $tagRepository
    ) {
        $this->dataPersistor = $dataPersistor;
        $this->TagFactory = $TagFactory
            ?: \Magento\Framework\App\ObjectManager::getInstance()->get(TagFactory::class);
        $this->tagRepository = $tagRepository
            ?: \Magento\Framework\App\ObjectManager::getInstance()->get(TagRepositoryInterface::class);
        parent::__construct($context, $date);
    }
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();
        if ($data) {
            if (isset($data['status']) && $data['status'] === 'true') {
                $data['status'] = Tag::STATUS_ENABLED;
            }
            if (empty($data['tag_id'])) {
                $data['tag_id'] = null;
            }

            /** @var \Lof\ProductTags\Model\Tag $model */
            $model = $this->TagFactory->create();

            $id = $this->getRequest()->getParam('tag_id');
            if ($id) {
                try {
                    $model = $this->tagRepository->getById($id);
                } catch (LocalizedException $e) {
                    $this->messageManager->addErrorMessage(__('This tag no longer exists.'));
                    return $resultRedirect->setPath('*/*/');
                }
            }
            $model->setData($data);
            try {
                $model->save($model);
                $this->messageManager->addSuccessMessage(__('You saved the tag.'));
                $this->dataPersistor->clear('lof_productags_tag');
                return $this->processBlockReturn($model, $data, $resultRedirect);
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the tag.'));
            }

            $this->dataPersistor->set('lof_productags_tag', $data);
            return $resultRedirect->setPath('*/*/edit', ['tag_id' => $id]);
        }
        return $resultRedirect->setPath('*/*/');
    }
    private function processBlockReturn($model, $data, $resultRedirect)
    {
        $redirect = $data['back'] ?? 'close';

        if ($redirect ==='continue') {
            $resultRedirect->setPath('*/*/edit', ['tag_id' => $model->getId()]);
        } else if ($redirect === 'close') {
            $resultRedirect->setPath('*/*/');
        } else if ($redirect === 'duplicate') {
            $duplicateModel = $this->TagFactory->create(['data' => $data]);
            $duplicateModel->setId(null);
            $duplicateModel->setIdentifier($data['identifier'] . '-' . uniqid());
            $duplicateModel->setIsActive(Tag::STATUS_DISABLED);
            $this->tagRepository->save($duplicateModel);
            $id = $duplicateModel->getId();
            $this->messageManager->addSuccessMessage(__('You duplicated the tag.'));
            $this->dataPersistor->set('lof_productags_tag', $data);
            $resultRedirect->setPath('*/*/edit', ['tag_id' => $id]);
        }
        return $resultRedirect;
    }
}
