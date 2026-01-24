Stop me if this sounds too complicated.

I'd like to use [docxtemplater](https://github.com/open-xml-templating/docxtemplater) to parse JSON files and add them to a 2026 resume template which can be dynamically served to anyone who requests it.

# Programmatic Resume

Install the docxtemplater, file-saver, and pizzip packages using npm. Include these dependencies in /resources/js/resume.js, which is a file strictly for resume purposes.

Create a config/resume.php with the following configuration properties

```yaml
resume:
  template: "/resources/resume/2026 resume template.docx"
  saved_documents: "/storage/app/resumes"
```

Then create the Resume Controller and add the web routes as defined below under [Routes](#Routes). all routes under `/resume` will require authentication and the "read-resume" permission. When an unauthenticated visitor accepts responses in text/html, redirect. For the other unauthenticated visitors, indicate they must go to /login page.

Create /resources/css/resume.css to house CSS. This will use tailwind @apply directives and do its best to emulate styles used on the home page for resume display.

When the user does not have the read-resume permission, they will receive a 403 error.

## New permissions to create

If bspdx/AuthKit allows new permissions to be created in the command line, do that. Otherwise create a migration to add these permissions.

- read-resume
- save-resume
- edit-resume

These permissions should be added to the admin and super-admin roles if they exist.

Read-resume should be added to a new role called "Resume Viewer" and both read-resume and save-resume should be added to a new role called "Recruiter."

## Accepted responses

Accepted mime types are are `text/html`, `text/plain`, and `application/json`.

Make your best judgement based on available information on how to format for each of these response types.

## Routes

The verbs used here may be unconventional but they do serve a purpose of obfuscation, so this is intentional. It also hopefully makes some sense.

### GET /resume

This will load /resources/resume/\*.json and display each in a very structured manner:

1. \***\*\<h1>Name\</h1>\*\*** - and then personal-information.json:name
1. **\<h2>Summary\</h2>** - and then personal-information.json:summary
1. **\<h2>Technical Skills\</h2>** - and then technical-skills.json
1. **\<h2>Experience\</h2>** - and then experience.json
1. **\<h2>Selected Projects\</h2>** - and then selected-projects.json


_(Note that education and technical profile are not displayed on this page.)_

Affixed to the bottom-right side of the page will be a FAB-style button with a download icon sourced from fontawesome, but this button only is shown when the user has the "save-resume" permission. Clicking it will send a POST to /resume/docx.

This page will do something insidious. If the user tries to print, the print CSS will hide the entire document except for name and summary, and underneath it will display the words "Please visit https://www.jasonvertucio.com/resume" to download the resume.

### POST /resume/docx


However, if the user does not have the "save-resume" permission, they will receive a 403 error.
This will generate a short-lived cookie (5 minutes, maybe) called 'hyperbole' with a value equal to the unix timestamp of the server and then redirect to GET /resume/docx.

### GET /resume/docx

First the cookie hyperbole will be validated. as long as it has a unix timestamp within 10 minutes of the server the visitor can proceed. if not, a 403 error will be returned with the message, "Direct download forbidden."

If request accepts application/json then the response will be formatted as json

```json
{
  "code": 403,
  "status": "failed",
  "message": "Direct download forbidden."
}
```

Otherwise:

This will load /resources/resume/_._ and send them to the blade template for parsing.

The file identified by config('resume.template') will be embedded as bas64/encoding, and each JSON file concatenated smartly so that it can be used in the doc.render function from DocxTemplater.

The blade template will utilize Docxtemplater, file-saver, and pizzip to create a Docx file from all of the data.

```typescript
// sample

const generateDocument = () => {
  const zip = new PizZip(content);
  const doc = new Docxtemplater(zip, {
    paragraphLoop: true,
    linebreaks: true,
    parser: expressionParser,
  });
  doc.render({});
  const out = doc.getZip().generate({
    type: "blob",
    mimeType:
      "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
  }); //Output the document using Data-URI
  saveAs(out, "output.docx");
};
```

In our case, the filename will be `<date in YYMMDDHHMMSS format> - Jasonvertucio.docx`. This file, once geenerated, will also have to be uploaded back to the server by sending it in the body of PUT /resume/docx

The page itself will only display something like

```markdown
# Jason's resume will download shortly.

If the file cannot be downloaded, please contact your administrator.
```

### POST /resume/docx/completed

This will accept a file upload and store the file in the directory specified by config('resume.saved_documents').

## File Types

```typescript
type Dates = Array<Date | string | number>;
type PersonalInformation = {
  name: string;
  title: string;
  email: string;
  phone: string;
  linkedin: string;
  summary: string;
};

type Education = {
  institution: string;
  degree: string;
  dates: Dates;
  description: string;
}[];

type Experience = {
  jobTitle: string;
  company: string;
  dates: Dates;
  location: string;
  bullets: string[];
}[];

type SelectedProjects = {
  projectName: string;
  description?: string;
  bullets: string[];
}[];

type TechnicalProfileCategory = {
  category: string;
  skills: { skill: string }[];
};

type TechnicalProfiles = {
  main: TechnicalProfileCategory[];
  secondary: TechnicalProfileCategory[];
};

type TechnicalSkillCategory = {
    title: string
list: string[]
}

type TechnicalSkills = {
    top: TechnicalSkillCategory[];
    other: TechnicalSkillCategory[];
}
```

# Future work

Create another migration to create
- the permissions 'admin' and 'manage-unauthenticated-viewers' and add to the admin and super-admin roles
- a new table (and matching model) for providing and storing share codes called resume_share_codes
    - id varchar(16)
    - expiration date
    - softDeletes
- a new table (and matching model) for storing usage of these share codes called resume_views
    - id unsignedBigInt
    - share_code_id varchar(16) FK to resume_share_codes.id
    - ip (max length of ipv6)
    - user agent (max length of user agent)
    - softDeletes

Users who have access to manage unauthenticated-viewers can

- view all codes as well every time the code was used to view the resume. 
- create new codes
- invalidate existing codes (functionally soft-deleting them)
- all of this functionality will be housed under the /admin/resume route.
    - if /admin route has to be created, create a basic admin page that looks like the normal app layout
    - only has one link to /admin/resume
    - user must have 

These codes will be implemented into the /resume routes and any visitor with that code will effectively have read-resume and save-resume permissions.
