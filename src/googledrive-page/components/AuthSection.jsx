import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import '../scss/components/AuthSection.scss';

const AuthSection = ({ onAuth, onDisconnect, isAuthenticated, isLoading }) => {
	const [isDisconnecting, setIsDisconnecting] = useState(false);

	const handleAuth = async () => {
		try {
			await onAuth();
		} catch (err) {
			console.error('Auth error:', err);
		}
	};

	const handleDisconnect = async () => {
		if (!confirm(__('Disconnect from Google Drive? You\'ll lose access and will need to authenticate again.', 'wpmudev-plugin-test'))) {
			return;
		}

		setIsDisconnecting(true);
		try {
			await onDisconnect();
		} catch (err) {
			console.error('Disconnect error:', err);
		} finally {
			setIsDisconnecting(false);
		}
	};

	return (
		<div className="auth-section">
			<div className="sui-box">
				<div className="sui-box-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
					<h3 className="sui-box-title">
						{__('Authentication', 'wpmudev-plugin-test')}
					</h3>
					{isAuthenticated && (
						<div className="sui-box-actions">
							<span className="sui-button sui-button-green sui-button-sm">
								{__('Connected', 'wpmudev-plugin-test')}
							</span>
						</div>
					)}
				</div>
				<div className="sui-box-body">
					{isAuthenticated ? (
						<div className="auth-section__success">
							<div className="sui-notice sui-notice-success">
								<div className="sui-notice-content">
									<div className="sui-notice-message">
										<span className="sui-notice-icon sui-icon-check-tick" aria-hidden="true"></span>
										<p>{__('Successfully authenticated with Google Drive!', 'wpmudev-plugin-test')}</p>
									</div>
								</div>
							</div>
							<div className="auth-section__info">
								<p className="sui-description">
									{__('You can now upload files, create folders, and manage your Google Drive content.', 'wpmudev-plugin-test')}
								</p>
								<div className="auth-section__actions">
									<button
										className="sui-button sui-button-blue sui-button-sm"
										onClick={handleDisconnect}
									>
										{isDisconnecting ? (
											<>
												<span className="sui-icon-loader sui-loading" aria-hidden="true"></span>
												{__('Disconnecting...', 'wpmudev-plugin-test')}
											</>
										) : (
											<>
												{__('Disconnect', 'wpmudev-plugin-test')}
											</>
										)}
									</button>
								</div>
							</div>
						</div>
					) : (
						<div className="auth-section__auth">
							<div className="auth-section__content">
								<h4>{__('Connect to Google Drive', 'wpmudev-plugin-test')}</h4>
								<p className="sui-description">
									{__('Click the button below to authenticate with Google Drive and start managing your files.', 'wpmudev-plugin-test')}
								</p>
								<button
									className="sui-button sui-button-blue sui-button-lg"
									onClick={handleAuth}
									disabled={isLoading}
								>
									{__('Authenticate with Google', 'wpmudev-plugin-test')}
								</button>
							</div>
						</div>
					)}
				</div>
			</div>
		</div>
	);
};

export default AuthSection;
