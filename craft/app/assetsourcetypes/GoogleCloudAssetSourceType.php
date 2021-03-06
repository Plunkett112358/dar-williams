<?php
namespace Craft;

craft()->requireEdition(Craft::Pro);

/**
 * The Google Cloud asset source type class. Handles the implementation of Google Cloud as an asset source type in
 * Craft.
 *
 * @author     Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright  Copyright (c) 2014, Pixel & Tonic, Inc.
 * @license    http://buildwithcraft.com/license Craft License Agreement
 * @see        http://buildwithcraft.com
 * @package    craft.app.assetsourcetypes
 * @since      1.0
 * @deprecated This class will be removed in Craft 3.0.
 */
class GoogleCloudAssetSourceType extends BaseAssetSourceType
{
	// Properties
	// =========================================================================

	/**
	 * @var string
	 */
	private static $_endpoint = 'storage.googleapis.com';

	/**
	 * @var \GC
	 */
	private $_googleCloud;

	// Public Methods
	// =========================================================================

	/**
	 * Get bucket list with credentials.
	 *
	 * @param $keyId
	 * @param $secret
	 *
	 * @throws Exception
	 * @return array
	 */
	public static function getBucketList($keyId, $secret)
	{
		$googleCloud = new \GC($keyId, $secret);
		$buckets = @$googleCloud->listBuckets();

		if (empty($buckets))
		{
			throw new Exception(Craft::t("Credentials rejected by target host."));
		}

		$bucketList = array();

		foreach ($buckets as $bucket)
		{
			$location = $googleCloud->getBucketLocation($bucket);

			$bucketList[] = array(
				'bucket' => $bucket,
				'location' => $location,
				'urlPrefix' => 'http://'.static::$_endpoint.'/'.$bucket.'/'
			);

		}

		return $bucketList;
	}

	/**
	 * @inheritDoc BaseAssetSourceType::getLocalCopy()
	 *
	 * @param AssetFileModel $file
	 *
	 * @return mixed
	 */

	public function getLocalCopy(AssetFileModel $file)
	{
		$location = AssetsHelper::getTempFilePath($file->getExtension());

		$this->_prepareForRequests();
		$this->_googleCloud->getObject($this->getSettings()->bucket, $this->_getGCPath($file), $location);

		return $location;
	}

	/**
	 * @inheritDoc BaseAssetSourceType::startIndex()
	 *
	 * @param $sessionId
	 *
	 * @return array
	 */
	public function startIndex($sessionId)
	{
		$settings = $this->getSettings();
		$this->_prepareForRequests();

		$offset = 0;
		$total = 0;

		$prefix = $this->_getPathPrefix();
		$fileList = $this->_googleCloud->getBucket($settings->bucket, $prefix);

		$fileList = array_filter($fileList, function($value)
		{
			$path = $value['name'];

			$segments = explode('/', $path);
			// Ignore the file
			array_pop($segments);

			foreach ($segments as $segment)
			{
				if (isset($segment[0]) && $segment[0] == '_')
				{
					return false;
				}
			}

			return true;
		});

		$bucketFolders = array();

		foreach ($fileList as $file)
		{
			// Strip the prefix, so we don't index the parent folders
			$file['name'] = mb_substr($file['name'], mb_strlen($prefix));

			if (!preg_match(AssetsHelper::INDEX_SKIP_ITEMS_PATTERN, $file['name']))
			{
				// In S3, it's possible to have files in folders that don't exist. e.g. - one/two/three.jpg. If folder
				// "one" is empty, except for folder "two", then "one" won't show up in this list so we work around it.

				// Matches all paths with folders, except if folder is last or no folder at all.
				if (preg_match('/(.*\/).+$/', $file['name'], $matches))
				{
					$folders = explode('/', rtrim($matches[1], '/'));
					$basePath = '';

					foreach ($folders as $folder)
					{
						$basePath .= $folder .'/';

						// This is exactly the case referred to above
						if ( ! isset($bucketFolders[$basePath]))
						{
							$bucketFolders[$basePath] = true;
						}
					}
				}

				if (mb_substr($file['name'], -1) == '/')
				{
					$bucketFolders[$file['name']] = true;
				}
				else
				{
					$indexEntry = array(
						'sourceId' => $this->model->id,
						'sessionId' => $sessionId,
						'offset' => $offset++,
						'uri' => $file['name'],
						'size' => $file['size']
					);

					craft()->assetIndexing->storeIndexEntry($indexEntry);
					$total++;
				}
			}
		}

		$indexedFolderIds = array();
		$indexedFolderIds[craft()->assetIndexing->ensureTopFolder($this->model)] = true;

		// Ensure folders are in the DB
		foreach ($bucketFolders as $fullPath => $nothing)
		{
			$folderId = $this->ensureFolderByFullPath($fullPath);
			$indexedFolderIds[$folderId] = true;
		}

		$missingFolders = $this->getMissingFolders($indexedFolderIds);

		return array('sourceId' => $this->model->id, 'total' => $total, 'missingFolders' => $missingFolders);
	}

	/**
	 * @inheritDoc BaseAssetSourceType::processIndex()
	 *
	 * @param $sessionId
	 * @param $offset
	 *
	 * @return mixed
	 */
	public function processIndex($sessionId, $offset)
	{
		$indexEntryModel = craft()->assetIndexing->getIndexEntry($this->model->id, $sessionId, $offset);

		if (empty($indexEntryModel))
		{
			return false;
		}

		$uriPath = $indexEntryModel->uri;
		$fileModel = $this->indexFile($uriPath);
		$this->_prepareForRequests();

		if ($fileModel)
		{
			$settings = $this->getSettings();

			craft()->assetIndexing->updateIndexEntryRecordId($indexEntryModel->id, $fileModel->id);

			$fileModel->size = $indexEntryModel->size;

			$fileInfo = $this->_googleCloud->getObjectInfo($settings->bucket, $this->_getPathPrefix().$uriPath);

			$targetPath = craft()->path->getAssetsImageSourcePath().$fileModel->id.'.'.IOHelper::getExtension($fileModel->filename);

			$timeModified = new DateTime('@'.$fileInfo['time']);

			if ($fileModel->kind == 'image' && ($fileModel->dateModified != $timeModified || !IOHelper::fileExists($targetPath)))
			{
				$this->_googleCloud->getObject($settings->bucket, $this->_getPathPrefix().$indexEntryModel->uri, $targetPath);
				clearstatcache();

				list ($width, $height) = ImageHelper::getImageSize($targetPath);

				$fileModel->width = $width;
				$fileModel->height = $height;

				// Store the local source or delete - maxCacheCloudImageSize is king.
				craft()->assetTransforms->storeLocalSource($targetPath, $targetPath);
				craft()->assetTransforms->queueSourceForDeletingIfNecessary($targetPath);
			}

			$fileModel->dateModified = $timeModified;

			craft()->assets->storeFile($fileModel);

			return $fileModel->id;
		}

		return false;
	}

	/**
	 * @inheritDoc IComponentType::getName()
	 *
	 * @return string
	 */
	public function getName()
	{
		return 'Google Cloud Storage';
	}

	/**
	 * @inheritDoc ISavableComponentType::getSettingsHtml()
	 *
	 * @return string|null
	 */
	public function getSettingsHtml()
	{
		$settings = $this->getSettings();
		$settings->expires = $this->extractExpiryInformation($settings->expires);

		return craft()->templates->render('_components/assetsourcetypes/GoogleCloud/settings', array(
			'settings' => $settings,
			'periods' => array_merge(array('' => ''), $this->getPeriodList())
		));
	}

	/**
	 * Get the timestamp of when a file transform was last modified.
	 *
	 * @param AssetFileModel $fileModel
	 * @param string         $transformLocation
	 *
	 * @return mixed
	 */
	public function getTimeTransformModified(AssetFileModel $fileModel, $transformLocation)
	{
		$folder = $fileModel->getFolder();
		$path = $this->_getPathPrefix().$folder->path.$transformLocation.'/'.$fileModel->filename;
		$this->_prepareForRequests();
		$info = $this->_googleCloud->getObjectInfo($this->getSettings()->bucket, $path);

		if (empty($info))
		{
			return false;
		}

		return new DateTime('@'.$info['time']);
	}

	/**
	 * @inheritDoc BaseAssetSourceType::getImageSourcePath()
	 *
	 * @param AssetFileModel $file
	 *
	 * @return mixed
	 */
	public function getImageSourcePath(AssetFileModel $file)
	{
		return craft()->path->getAssetsImageSourcePath().$file->id.'.'.IOHelper::getExtension($file->filename);
	}

	/**
	 * Return true if a transform exists at the location for a file.
	 *
	 * @param AssetFileModel $file
	 * @param                $location
	 *
	 * @return mixed
	 */
	public function transformExists(AssetFileModel $file, $location)
	{
		$this->_prepareForRequests();
		return (bool) @$this->_googleCloud->getObjectInfo($this->getSettings()->bucket, $this->_getPathPrefix().$file->getFolder()->path.$location.'/'.$file->filename);
	}

	/**
	 * @inheritDoc BaseAssetSourceType::fileExists()
	 *
	 * @param string $parentPath  Parent path
	 * @param string $filename    The name of the file.
	 *
	 * @return boolean
	 */
	public function fileExists($parentPath, $fileName)
	{
		$this->_prepareForRequests();
		return (bool) $this->_googleCloud->getObjectInfo($this->getSettings()->bucket, rtrim($this->_getPathPrefix().$parentPath, '/').'/'.$fileName);
	}

	/**
	 * @inheritDoc BaseAssetSourceType::folderExists()
	 *
	 * @param string $parentPath  Parent path
	 * @param string $folderName
	 *
	 * @return boolean
	 */
	public function folderExists($parentPath, $folderName)
	{
		$this->_prepareForRequests();
		return (bool) $this->_googleCloud->getObjectInfo($this->getSettings()->bucket, $this->_getPathPrefix().$parentPath.rtrim($folderName, '/').'/');
	}

	/**
	 * @inheritDoc BaseAssetSourceType::getBaseUrl()
	 *
	 * @return string
	 */
	public function getBaseUrl()
	{
		return $this->getSettings()->urlPrefix.$this->_getPathPrefix();
	}

	/**
	 * @inheritDoc BaseAssetSourceType::isRemote()
	 *
	 * @return bool
	 */
	public function isRemote()
	{
		return true;
	}

	// Protected Methods
	// =========================================================================

	/**
	 * @inheritDoc BaseSavableComponentType::defineSettings()
	 *
	 * @return array
	 */
	protected function defineSettings()
	{
		return array(
			'keyId'      => array(AttributeType::String, 'required' => true),
			'secret'     => array(AttributeType::String, 'required' => true),
			'bucket'     => array(AttributeType::String, 'required' => true),
			'urlPrefix'  => array(AttributeType::String, 'required' => true),
			'subfolder'  => array(AttributeType::String, 'default' => ''),
			'expires'    => array(AttributeType::String, 'default' => ''),
		);
	}

	/**
	 * @inheritDoc BaseAssetSourceType::insertFileInFolder()
	 *
	 * @param AssetFolderModel $folder
	 * @param                  $filePath
	 * @param                  $fileName
	 *
	 * @throws Exception
	 * @return AssetFileModel
	 */
	protected function insertFileInFolder(AssetFolderModel $folder, $filePath, $fileName)
	{
		$fileName = AssetsHelper::cleanAssetName($fileName);
		$extension = IOHelper::getExtension($fileName);

		if (!IOHelper::isExtensionAllowed($extension))
		{
			throw new Exception(Craft::t('This file type is not allowed'));
		}

		$uriPath = $this->_getPathPrefix().$folder->path.$fileName;

		$this->_prepareForRequests();
		$settings = $this->getSettings();
		$fileInfo = $this->_googleCloud->getObjectInfo($settings->bucket, $uriPath);

		if ($fileInfo)
		{
			$response = new AssetOperationResponseModel();
			return $response->setPrompt($this->getUserPromptOptions($fileName))->setDataItem('fileName', $fileName);
		}

		clearstatcache();
		$this->_prepareForRequests();

		if (!$this->putObject($filePath, $this->getSettings()->bucket, $uriPath, \GC::ACL_PUBLIC_READ))
		{
			throw new Exception(Craft::t('Could not copy file to target destination'));
		}

		$response = new AssetOperationResponseModel();
		return $response->setSuccess()->setDataItem('filePath', $uriPath);
	}

	/**
	 * @inheritDoc BaseAssetSourceType::getNameReplacement()
	 *
	 * @param AssetFolderModel $folder
	 * @param                  $fileName
	 *
	 * @return mixed
	 */
	protected function getNameReplacement(AssetFolderModel $folder, $fileName)
	{
		$this->_prepareForRequests();
		$fileList = $this->_googleCloud->getBucket($this->getSettings()->bucket, $this->_getPathPrefix().$folder->path);

		$fileList = array_flip(array_map('mb_strtolower', array_keys($fileList)));

		// Double-check
		if (!isset($fileList[mb_strtolower($this->_getPathPrefix().$folder->path.$fileName)]))
		{
			return $fileName;
		}

		$fileNameParts = explode(".", $fileName);
		$extension = array_pop($fileNameParts);

		$fileNameStart = join(".", $fileNameParts).'_';
		$index = 1;

		while (isset($fileList[mb_strtolower($this->_getPathPrefix().$folder->path.$fileNameStart.$index.'.'.$extension)]))
		{
			$index++;
		}

		return $fileNameStart.$index.'.'.$extension;
	}

	/**
	 * @inheritDoc BaseAssetSourceType::deleteSourceFile()
	 *
	 * @param string $subpath
	 *
	 * @return void
	 */
	protected function deleteSourceFile($subpath)
	{
		$this->_prepareForRequests();
		@$this->_googleCloud->deleteObject($this->getSettings()->bucket, $this->_getPathPrefix().$subpath);
	}

	/**
	 * @inheritDoc BaseAssetSourceType::moveSourceFile()
	 *
	 * @param AssetFileModel   $file
	 * @param AssetFolderModel $targetFolder
	 * @param string           $fileName
	 * @param bool             $overwrite
	 *
	 * @return mixed
	 */
	protected function moveSourceFile(AssetFileModel $file, AssetFolderModel $targetFolder, $fileName = '', $overwrite = false)
	{
		if (empty($fileName))
		{
			$fileName = $file->filename;
		}

		$newServerPath = $this->_getPathPrefix().$targetFolder->path.$fileName;

		$conflictingRecord = craft()->assets->findFile(array(
			'folderId' => $targetFolder->id,
			'filename' => $fileName
		));

		$this->_prepareForRequests();
		$settings = $this->getSettings();
		$fileInfo = $this->_googleCloud->getObjectInfo($settings->bucket, $newServerPath);

		$conflict = !$overwrite && ($fileInfo || (!craft()->assets->isMergeInProgress() && is_object($conflictingRecord)));

		if ($conflict)
		{
			$response = new AssetOperationResponseModel();
			return $response->setPrompt($this->getUserPromptOptions($fileName))->setDataItem('fileName', $fileName);
		}


		$bucket = $this->getSettings()->bucket;

		// Just in case we're moving from another bucket with the same access credentials.
		$originatingSourceType = craft()->assetSources->getSourceTypeById($file->sourceId);
		$originatingSettings = $originatingSourceType->getSettings();
		$sourceBucket = $originatingSettings->bucket;

		$this->_prepareForRequests($originatingSettings);

		if (!$this->_googleCloud->copyObject($sourceBucket, $this->_getPathPrefix($originatingSettings).$file->getFolder()->path.$file->filename, $bucket, $newServerPath, \GC::ACL_PUBLIC_READ))
		{
			$response = new AssetOperationResponseModel();
			return $response->setError(Craft::t("Could not save the file"));
		}

		@$this->_googleCloud->deleteObject($sourceBucket, $this->_getGCPath($file, $originatingSettings));

		if ($file->kind == 'image')
		{
			if ($targetFolder->sourceId == $file->sourceId)
			{
				$transforms = craft()->assetTransforms->getAllCreatedTransformsForFile($file);

				$destination = clone $file;
				$destination->filename = $fileName;

				// Move transforms
				foreach ($transforms as $index)
				{
					// For each file, we have to have both the source and destination
					// for both files and transforms, so we can reliably move them
					$destinationIndex = clone $index;

					if (!empty($index->filename))
					{
						$destinationIndex->filename = $fileName;
						craft()->assetTransforms->storeTransformIndexData($destinationIndex);
					}

					$from = $this->_getPathPrefix($originatingSettings).$file->getFolder()->path.craft()->assetTransforms->getTransformSubpath($file, $index);
					$to   = $this->_getPathPrefix().$targetFolder->path.craft()->assetTransforms->getTransformSubpath($destination, $destinationIndex);

					$this->copySourceFile($from, $to);
					$this->deleteSourceFile($from);
				}
			}
			else
			{
				craft()->assetTransforms->deleteAllTransformData($file);
			}
		}

		$response = new AssetOperationResponseModel();
		return $response->setSuccess()
				->setDataItem('newId', $file->id)
				->setDataItem('newFileName', $fileName);
	}

	/**
	 * @inheritDoc BaseAssetSourceType::createSourceFolder()
	 *
	 * @param AssetFolderModel $parentFolder
	 * @param                  $folderName
	 *
	 * @return bool
	 */
	protected function createSourceFolder(AssetFolderModel $parentFolder, $folderName)
	{
		$this->_prepareForRequests();
		return $this->putObject('', $this->getSettings()->bucket, $this->_getPathPrefix().rtrim($parentFolder->path.$folderName, '/').'/', \GC::ACL_PUBLIC_READ);
	}

	/**
	 * @inheritDoc BaseAssetSourceType::renameSourceFolder()
	 *
	 * @param AssetFolderModel $folder
	 * @param                  $newName
	 *
	 * @return bool
	 */
	protected function renameSourceFolder(AssetFolderModel $folder, $newName)
	{
		$newFullPath = $this->_getPathPrefix().IOHelper::getParentFolderPath($folder->path).$newName.'/';

		$this->_prepareForRequests();
		$bucket = $this->getSettings()->bucket;
		$filesToMove = $this->_googleCloud->getBucket($bucket, $this->_getPathPrefix().$folder->path);

		rsort($filesToMove);

		foreach ($filesToMove as $file)
		{
			$filePath = mb_substr($file['name'], mb_strlen($this->_getPathPrefix().$folder->path));

			$this->_googleCloud->copyObject($bucket, $file['name'], $bucket, $newFullPath.$filePath, \GC::ACL_PUBLIC_READ);
			@$this->_googleCloud->deleteObject($bucket, $file['name']);
		}

		return true;
	}

	/**
	 * @inheritDoc BaseAssetSourceType::deleteSourceFolder()
	 *
	 * @param AssetFolderModel $parentFolder
	 * @param                  $folderName
	 *
	 * @return bool
	 */
	protected function deleteSourceFolder(AssetFolderModel $parentFolder, $folderName)
	{
		$this->_prepareForRequests();
		$bucket = $this->getSettings()->bucket;
		$objectsToDelete = $this->_googleCloud->getBucket($bucket, $this->_getPathPrefix().$parentFolder->path.$folderName);

		foreach ($objectsToDelete as $uri)
		{
			@$this->_googleCloud->deleteObject($bucket, $uri['name']);
		}

		return true;
	}

	/**
	 * @inheritDoc BaseAssetSourceType::putImageTransform()
	 *
	 * @param AssetFileModel           $file
	 * @param AssetTransformIndexModel $index
	 * @param string                   $sourceImage
	 *
	 * @return mixed
	 */
	public function putImageTransform(AssetFileModel $file, AssetTransformIndexModel $index, $sourceImage)
	{
		$this->_prepareForRequests();
		$targetFile = $this->_getPathPrefix().$file->getFolder()->path.craft()->assetTransforms->getTransformSubpath($file, $index);

		return $this->putObject($sourceImage, $this->getSettings()->bucket, $targetFile, \GC::ACL_PUBLIC_READ);
	}

	/**
	 * Put an object into an S3 bucket.
	 *
	 * @param $filePath
	 * @param $bucket
	 * @param $uriPath
	 * @param $permissions
	 *
	 * @return bool
	 */
	protected function putObject($filePath, $bucket, $uriPath, $permissions)
	{
		$object = empty($filePath) ? '' : array('file' => $filePath);
		$headers = array();

		if (!empty($object) && !empty($this->getSettings()->expires) && DateTimeHelper::isValidIntervalString($this->getSettings()->expires))
		{
			$expires = new DateTime();
			$now = new DateTime();
			$expires->modify('+'.$this->getSettings()->expires);
			$diff = $expires->format('U') - $now->format('U');
			$headers['Cache-Control'] = 'max-age='.$diff.', must-revalidate';
		}

		return $this->_googleCloud->putObject($object, $bucket, $uriPath, $permissions, array(), $headers);
	}

	/**
	 * @inheritDoc BaseAssetSourceType::canMoveFileFrom()
	 *
	 * @param BaseAssetSourceType $originalSource
	 *
	 * @return mixed
	 */
	protected function canMoveFileFrom(BaseAssetSourceType $originalSource)
	{
		if ($this->model->type == $originalSource->model->type)
		{
			$settings = $originalSource->getSettings();
			$theseSettings = $this->getSettings();
			if ($settings->keyId == $theseSettings->keyId && $settings->secret == $theseSettings->secret)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * @inheritDoc BaseAssetSourceType::copySourceFile()
	 *
	 * @param $sourceUri
	 * @param $targetUri
	 *
	 * @return bool
	 */
	protected function copySourceFile($sourceUri, $targetUri)
	{
		if ($sourceUri == $targetUri)
		{
			return true;
		}

		$bucket = $this->getSettings()->bucket;
		return (bool) @$this->_googleCloud->copyObject($bucket, $sourceUri, $bucket, $targetUri, \GC::ACL_PUBLIC_READ);
	}

	// Private Methods
	// =========================================================================

	/**
	 * Return a prefix for S3 path for settings.
	 *
	 * @param object|null $settings The settings to use. If null, will use current settings.
	 *
	 * @return string
	 */
	private function _getPathPrefix($settings = null)
	{
		if (is_null($settings))
		{
			$settings = $this->getSettings();
		}

		if (!empty($settings->subfolder))
		{
			return rtrim($settings->subfolder, '/').'/';
		}

		return '';
	}

	/**
	 * Get a file's S3 path.
	 *
	 * @param AssetFileModel $file
	 * @param                $settings The source settings to use.
	 *
	 * @return string
	 */
	private function _getGCPath(AssetFileModel $file, $settings = null)
	{
		$folder = $file->getFolder();
		return $this->_getPathPrefix($settings).$folder->path.$file->filename;
	}

	/**
	 * Prepare the S3 connection for requests to this bucket.
	 *
	 * @param $settings
	 *
	 * @return null
	 */
	private function _prepareForRequests($settings = null)
	{
		if (is_null($settings))
		{
			$settings = $this->getSettings();
		}

		if (is_null($this->_googleCloud))
		{
			$this->_googleCloud = new \GC($settings->keyId, $settings->secret);
		}

		\GC::setAuth($settings->keyId, $settings->secret);
	}
}
