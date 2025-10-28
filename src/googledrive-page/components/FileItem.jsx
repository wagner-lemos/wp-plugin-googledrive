import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import '../scss/components/FileItem.scss';

const FileItem = ({ file, onDownload }) => {
	const [isDownloading, setIsDownloading] = useState(false);

	const handleDownload = async () => {
		setIsDownloading(true);
		try {
			await onDownload(file.id);
		} catch (err) {
			console.error('Download error:', err);
		} finally {
			setIsDownloading(false);
		}
	};

	const formatFileSize = (bytes) => {
		if (!bytes) return '';
		const sizes = ['Bytes', 'KB', 'MB', 'GB'];
		const i = Math.floor(Math.log(bytes) / Math.log(1024));
		return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
	};

	const formatDate = (dateString) => {
		return new Date(dateString).toLocaleDateString();
	};

	const getFileIcon = (mimeType) => {
		if (mimeType === 'application/vnd.google-apps.folder') {
			return __('Folder', 'wpmudev-plugin-test');
		}

		// Determine icon based on file type
		if (mimeType.startsWith('image/')) {
			return __('IMG', 'wpmudev-plugin-test')
		} else if (mimeType.startsWith('video/')) {
			return __('Video', 'wpmudev-plugin-test');
		} else if (mimeType.startsWith('audio/')) {
			return __('Audio', 'wpmudev-plugin-test');
		} else if (mimeType.includes('pdf')) {
			return __('PDF', 'wpmudev-plugin-test');
		} else if (mimeType.includes('word') || mimeType.includes('document')) {
			return __('Document', 'wpmudev-plugin-test');
		} else if (mimeType.includes('sheet') || mimeType.includes('excel')) {
			return __('Spreadsheet', 'wpmudev-plugin-test');
		} else if (mimeType.includes('presentation') || mimeType.includes('powerpoint')) {
			return __('Presentation', 'wpmudev-plugin-test');
		} else {
			return __('File', 'wpmudev-plugin-test');
		}
	};

	const isFolder = file.mimeType === 'application/vnd.google-apps.folder';

	return (
		<div className="file-item">
			<div className="file-item__content">
				<div className="file-item__info">
					<div className="file-item__details">
						<h4 className="file-item__name">{file.name}</h4>
						<div className="file-item__meta">
							<span className="file-item__type">
								{getFileIcon(file.mimeType)}
							</span>
							{!isFolder && file.size && (
								<span className="file-item__size">
									{formatFileSize(file.size)}
								</span>
							)}
							<span className="file-item__date">
								{formatDate(file.modifiedTime)}
							</span>
						</div>
					</div>
				</div>
				<div className="file-item__actions">
					{!isFolder && (
						<button
							className="sui-button sui-button-green sui-button-sm"
							onClick={handleDownload}
							disabled={isDownloading}
							title={__('Download file', 'wpmudev-plugin-test')}
						>
							{isDownloading ? (
								<span className="sui-icon-loader sui-loading" aria-hidden="true"></span>
							) : (
								<span className="sui-icon-download" aria-hidden="true"></span>
							)}
							{isDownloading ? __('Downloading...', 'wpmudev-plugin-test') : __('Download', 'wpmudev-plugin-test')}
						</button>
					)}
					{file.webViewLink && (
						<a
							href={file.webViewLink}
							target="_blank"
							rel="noopener noreferrer"
							className="sui-button sui-button-green sui-button-sm"
							title={__('View in Google Drive', 'wpmudev-plugin-test')}
						>
							<span className="sui-icon-external-link" aria-hidden="true"></span>
							{__('View in Drive', 'wpmudev-plugin-test')}
						</a>
					)}
				</div>
			</div>
		</div>
	);
};

export default FileItem;
