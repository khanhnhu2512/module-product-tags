<?php
namespace Lof\ProductTags\Model;

use Lof\ProductTags\Api\TagRepositoryInterface;
use Lof\ProductTags\Api\Data\TagSearchResultsInterfaceFactory;
use Lof\ProductTags\Api\Data\TagInterfaceFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Reflection\DataObjectProcessor;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Lof\ProductTags\Model\ResourceModel\Tag as ResourceTag;
use Lof\ProductTags\Model\ResourceModel\Tag\CollectionFactory as TagCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface;
use Magento\Framework\Api\ExtensibleDataObjectConverter;

class TagRepository implements TagRepositoryInterface
{

    protected $resource;

    protected $tagFactory;

    protected $tagCollectionFactory;

    protected $searchResultsFactory;

    protected $dataObjectHelper;

    protected $dataObjectProcessor;

    protected $dataTagFactory;

    protected $extensionAttributesJoinProcessor;

    private $storeManager;

    private $collectionProcessor;

    protected $extensibleDataObjectConverter;

    /**
     * @param ResourceTag $resource
     * @param TagFactory $tagFactory
     * @param TagInterfaceFactory $dataTagFactory
     * @param TagCollectionFactory $tagCollectionFactory
     * @param TagSearchResultsInterfaceFactory $searchResultsFactory
     * @param DataObjectHelper $dataObjectHelper
     * @param DataObjectProcessor $dataObjectProcessor
     * @param StoreManagerInterface $storeManager
     * @param CollectionProcessorInterface $collectionProcessor
     * @param JoinProcessorInterface $extensionAttributesJoinProcessor
     * @param ExtensibleDataObjectConverter $extensibleDataObjectConverter
     */
    public function __construct(
        ResourceTag $resource,
        TagFactory $tagFactory,
        TagInterfaceFactory $dataTagFactory,
        TagCollectionFactory $tagCollectionFactory,
        TagSearchResultsInterfaceFactory $searchResultsFactory,
        DataObjectHelper $dataObjectHelper,
        DataObjectProcessor $dataObjectProcessor,
        StoreManagerInterface $storeManager,
        CollectionProcessorInterface $collectionProcessor,
        JoinProcessorInterface $extensionAttributesJoinProcessor,
        ExtensibleDataObjectConverter $extensibleDataObjectConverter
    ) {
        $this->resource = $resource;
        $this->tagFactory = $tagFactory;
        $this->tagCollectionFactory = $tagCollectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->dataObjectHelper = $dataObjectHelper;
        $this->dataTagFactory = $dataTagFactory;
        $this->dataObjectProcessor = $dataObjectProcessor;
        $this->storeManager = $storeManager;
        $this->collectionProcessor = $collectionProcessor;
        $this->extensionAttributesJoinProcessor = $extensionAttributesJoinProcessor;
        $this->extensibleDataObjectConverter = $extensibleDataObjectConverter;
    }

    /**
     * {@inheritdoc}
     */
    public function save(
        \Lof\ProductTags\Api\Data\TagInterface $tag
    ) {
        if (empty($tag->getStoreId())) {
            $storeId = $this->storeManager->getStore()->getId();
            $tag->setStoreId($storeId);
        }   
        $tagModel = $this->tagFactory->create()->setData($tagData);
        
        try {
            $this->resource->save($tagModel);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__(
                'Could not save the tag: %1',
                $exception->getMessage()
            ));
        }
        return $tagModel->getDataModel();
    }
    
    /**
     * {@inheritdoc}
     */
    public function getById($tagId)
    {
        $tag = $this->tagFactory->create();
        $this->resource->load($tag, $tagId);
        if (!$tag->getId()) {
            throw new NoSuchEntityException(__('Tag with id "%1" does not exist.', $tagId));
        }
        return $tag->getDataModel();
    }

    /**
     * {@inheritdoc}
     */
    public function getList(
        \Magento\Framework\Api\SearchCriteriaInterface $criteria
    ) {
        $collection = $this->tagCollectionFactory->create();
        
        $this->extensionAttributesJoinProcessor->process(
            $collection,
            \Lof\ProductTags\Api\Data\TagInterface::class
        );
        
        $this->collectionProcessor->process($criteria, $collection);
        
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($criteria);
        
        $items = [];
        foreach ($collection as $model) {
            $items[] = $model->getDataModel();
        }
        
        $searchResults->setItems($items);
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(
        \Lof\ProductTags\Api\Data\TagInterface $tag
    ) {
        try {
            $tagModel = $this->tagFactory->create();
            $this->resource->load($tagModel, $tag->getTagId());
            $this->resource->delete($tagModel);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__(
                'Could not delete the Tag: %1',
                $exception->getMessage()
            ));
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteById($tagId)
    {
        return $this->delete($this->getById($tagId));
    }
}
