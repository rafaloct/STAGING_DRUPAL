# Google Drive Documents Component

## Overview
A new component has been created to display Google Drive documents in study group (grupo_estudos) pages. The component automatically loads public documents from a Google Drive folder linked to the group.

## Files Created/Modified

### New Files
1. **`web/modules/custom/neruds_google_integration/js/google-drive-documents.js`**
   - Main JavaScript component that handles folder ID extraction and API calls
   - Groups documents by type (documents, spreadsheets, presentations, images, videos)
   - Renders documents with formatted dates and proper styling
   - Includes analytics tracking for document clicks

2. **`web/modules/custom/neruds_google_integration/css/google-drive-documents.css`**
   - Responsive styling for the component
   - Grid layout that adapts to mobile screens
   - Document grouping sections with icons
   - Loading, empty, and error states

### Modified Files
1. **`web/modules/custom/neruds_google_integration/neruds_google_integration.libraries.yml`**
   - Added new library definition for `google_drive_documents`
   - Registers JS and CSS dependencies

2. **`web/modules/custom/neruds_google_integration/neruds_google_integration.module`**
   - Updated `neruds_google_integration_preprocess_node()` function
   - Attaches the new library to `grupo_estudos` nodes

3. **`web/themes/custom/neruds_gui/templates/node/node--grupo-estudos--full.html.twig`**
   - Updated the "Documentos" tab section
   - Changed from `data-drive-folder` to `data-drive-documents` attribute
   - Uses new component markup

## How It Works

### Data Attributes
```html
<div data-drive-documents="{{ drive_url }}" data-drive-endpoint="/neruds/api/drive-folder">
```

- `data-drive-documents`: Full Google Drive folder URL
- `data-drive-endpoint`: API endpoint (defaults to `/neruds/api/drive-folder`)

### Features
1. **Folder ID Extraction**: Automatically extracts folder ID from Google Drive URL
2. **Document Organization**: Groups documents by MIME type
3. **Responsive Design**: Adapts to mobile and desktop screens
4. **Error Handling**: Graceful fallback messages for various error states
5. **Analytics Integration**: Tracks document views via Google Analytics
6. **Internationalization**: All text is translatable via Drupal t() function

### Document Types Supported
- Documents (Word, Google Docs, PDFs)
- Spreadsheets (Excel, Google Sheets)
- Presentations (PowerPoint, Google Slides)
- Images
- Videos
- Other files

## Integration with Existing Code
The component replaces the previous `data-drive-folder` implementation that was part of the `research-communities.js` behavior. The old `research_communities` library is still attached for other functionality (tab management, etc.).

## Testing
To test the component:

1. Navigate to a study group page (grupo_estudos)
2. Click on the "Documentos" (Documents) tab
3. The component should:
   - Display a loading message initially
   - Fetch documents from the linked Google Drive folder
   - Group documents by type
   - Show document count and folder link
   - Display each document with name and modification date

## API Endpoint
The component calls the existing API endpoint:
```
GET /neruds/api/drive-folder?folder={folder_id}
```

Expected response format:
```json
{
  "files": [
    {
      "name": "Document Name",
      "url": "https://drive.google.com/...",
      "mimeType": "application/vnd.google-apps.document",
      "modifiedTime": "2026-05-24T10:00:00Z"
    }
  ],
  "folderUrl": "https://drive.google.com/drive/folders/{folder_id}"
}
```

## Browser Support
- Chrome/Edge 80+
- Firefox 75+
- Safari 13+
- Mobile browsers with ES2019 support

## Notes
- The component uses `fetch()` API for HTTP requests
- Respects `credentials: 'same-origin'` for CORS
- All user input is sanitized using `Drupal.checkPlain()`
- CSS is mobile-first responsive design
