# v1.0.4 - GitHub Token Support

## 🔐 New Features

### ✅ GitHub Token Support
- **Private Repository Access**: Support for private GitHub repositories
- **Secure Authentication**: GitHub personal access token integration
- **Admin Configuration**: Easy token setup in plugin settings

### ✅ Enhanced Security
- **Private Repository**: Repository is now private for better security
- **Token Management**: Secure token storage and usage
- **Update Reliability**: Improved update checking for private repos

## 🔧 Technical Changes
- Added GitHub token configuration option
- Enhanced updater class with authentication support
- Updated settings page with token input field
- Added comprehensive documentation

## 📁 Files Modified
- `includes/class-licenceland-updater.php` - GitHub token authentication
- `includes/class-licenceland-settings.php` - Token configuration UI
- `uninstall.php` - Token cleanup on uninstall
- `languages/licenceland.pot` - Translation keys
- `README.md` - Updated documentation

## 🚀 How to Use
1. Go to **LicenceLand > Settings**
2. Enter your GitHub personal access token
3. Get token from: https://github.com/settings/tokens
4. Token needs `repo` scope for private repository access

## 🔒 Security Benefits
- Repository remains private
- Only authorized users can access updates
- Secure token handling
- No public exposure of code 