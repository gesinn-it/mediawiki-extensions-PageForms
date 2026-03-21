# PageForms Architecture

## Overview

PageForms is a MediaWiki extension that provides structured editing capabilities for wiki pages.

Instead of editing raw wikitext, users interact with forms that generate the corresponding page content based on predefined templates.

Forms are defined in the **Form namespace** and describe:

* templates used to generate content
* fields mapped to template parameters
* input widgets used for editing
* constraints and validation rules

The PageForms architecture transforms these form definitions into interactive HTML forms and ultimately into generated wiki text.

---

# Architectural Layers

PageForms can be understood as a pipeline consisting of several layers.

```
Form Definition (Form namespace)
        │
        ▼
Form Parsing
        │
        ▼
Domain Model
        │
        ▼
Form Rendering
        │
        ▼
Browser UI
        │
        ▼
Submission Processing
        │
        ▼
Wiki Page Generation
```

Each stage is handled by specific components within the extension.

---

# Core Domain Model

Form definitions are parsed into a set of PHP objects representing the structure of the form.

The core domain model consists of the following classes.

```
PFForm
PFTemplateInForm
PFFormField
PFTemplateField
```

### PFForm

Represents the complete form definition.

Responsibilities:

* parsing the form page
* managing templates used within the form
* coordinating rendering

---

### PFTemplateInForm

Represents a template used within a form.

Responsibilities:

* mapping template parameters to form fields
* controlling template multiplicity
* managing template-level configuration

---

### PFFormField

Represents a field defined in the form definition.

Responsibilities include:

* field configuration
* input type selection
* validation rules
* default values
* value sources

This class acts as the bridge between the form definition and the runtime input widget.

---

### PFTemplateField

Represents a field defined in the underlying template.

It stores metadata about the template parameter including:

* property mapping
* field type
* possible values
* delimiter rules

---

# Form Processing Pipeline

When a form page is accessed, the following processing steps occur.

```
Form definition page
        │
        ▼
PFForm parses form definition
        │
        ▼
PFTemplateInForm objects created
        │
        ▼
PFFormField objects created
        │
        ▼
Form rendering begins
```

The parsing stage extracts field configuration from the form markup and transforms it into the internal domain model.

---

# Form Rendering

Rendering of forms is handled primarily by:

```
PFFormPrinter
```

Responsibilities include:

* generating the HTML structure of the form
* initializing input widgets
* attaching client-side behavior
* preparing submission metadata

The renderer transforms the domain model into the interactive form presented in the browser.

---

# Page Generation Pipeline

When a form is submitted, PageForms generates wiki text representing the content of the page.

The generation pipeline looks like this:

```
Form submission
        │
        ▼
Field values collected
        │
        ▼
Template calls constructed
        │
        ▼
Wiki text generated
        │
        ▼
Page saved via MediaWiki
```

Key class involved:

```
PFWikiPage
```

This class manages the generation and updating of page content based on form submissions.

---

# Input System (Plugin Mechanism)

PageForms supports a flexible **input system** allowing different types of form widgets.

Examples include:

* text inputs
* dropdowns
* checkboxes
* comboboxes
* date pickers

Input types are defined through the parameter:

```
input type=
```

Example:

```
{{{field|Author|input type=combobox}}}
```

Internally, PageForms resolves input types using a **plugin mechanism**.

Input widgets are implemented as separate components that receive configuration arguments and render their HTML representation.

This architecture allows extensions to introduce additional input types without modifying the core PageForms logic.

---

# Client-Side Architecture

PageForms uses JavaScript to enhance the interactive behavior of forms.

Client logic is implemented using:

```
ResourceLoader modules
```

Key JavaScript components include:

```
PageForms.js
PF_formInput.js
```

Responsibilities of the client layer include:

* widget initialization
* form validation
* dynamic field behavior
* asynchronous interactions

The client layer interacts with MediaWiki APIs where necessary.

---

# Integration with MediaWiki

PageForms integrates deeply with MediaWiki through several mechanisms.

### Special Pages

Special pages provide entry points for form interaction.

Examples include:

```
Special:FormEdit
Special:RunQuery
```

---

### Parser Functions

PageForms provides parser functions that allow forms to be embedded in wiki pages.

Examples include:

```
#forminput
#formlink
#autoedit
```

These functions act as the primary interface between normal wiki pages and PageForms functionality.

---

### Hooks

The extension registers MediaWiki hooks for:

* form rendering
* page editing integration
* parser behavior

These hooks enable PageForms to integrate with the MediaWiki editing workflow.

---

# MediaWiki Version Compatibility

PageForms supports a range of MediaWiki versions. Where the MW or PHP API
differs between versions, the compatibility gap is encapsulated in a static
helper on `PFUtils` rather than repeated at each call site:

```php
// DB reads — use the centralized helper:
$db = PFUtils::getReplicaDB();

// WikiPage from Title — use the centralized helper:
$wikiPage = PFUtils::newWikiPageFromTitle( $title );
```

These helpers contain the version gate internally and shield all other code
from knowing which MW version is running.

Key compatibility boundaries:

| Boundary | Older API | Newer API | Since | PFUtils helper |
|----------|-----------|-----------|-------|----------------|
| DB replica connection | `wfGetDB( DB_REPLICA )` | `getConnectionProvider()->getReplicaDatabase()` | MW 1.42 | `PFUtils::getReplicaDB()` |
| WikiPage from Title | `WikiPage::factory( $title )` | `getWikiPageFactory()->newFromTitle( $title )` | MW 1.36 | `PFUtils::newWikiPageFromTitle( $title )` |
| DB table JOIN | Raw SQL string as table name | Array form with `$joinConds` | MW 1.43 | — |
| Parser instance | `$parser->getFreshParser()` | `$parser` directly (removed) | MW 1.43 | — |
| ResourceLoader type hint | `ResourceLoader $rl` | No type hint | MW 1.43 | — |
| Add user to group | `User::addGroup()` | `UserGroupManager::addUserToGroup()` | MW 1.41 | — |

Test infrastructure notes:

* `phpunit.xml.dist` sets `convertWarningsToExceptions="true"` — any
  `E_WARNING` or `E_DEPRECATED` on PHP 8 becomes a test exception. Null values
  passed to string functions must be guarded before reaching PHP 8 code.
* MW 1.43 test bootstrap sets `$wgArticlePath = '/wiki/$1'`. JSONScript tests
  that assert URL paths must pin `wgArticlePath` in their `settings` block.
* MW 1.43 ooui uses Web Components — `global.HTMLElement = window.HTMLElement`
  must be set in the node-qunit `setup.js`.

---

# Extension Points

PageForms is designed to be extensible.

Key extension points include:

### Input Types

Developers can introduce new input widgets.

### Data Sources

Fields can retrieve values from external data sources.

### Parser Functions

Additional parser functions may be added for specialized workflows.

---

# Known Architectural Issues

PageForms has evolved over many years and contains some legacy architectural patterns.

Examples include:

* mixing of domain and rendering responsibilities
* historical coupling with specific extensions
* global configuration variables influencing runtime behavior

Future refactoring could focus on improving separation of concerns and modularization.
