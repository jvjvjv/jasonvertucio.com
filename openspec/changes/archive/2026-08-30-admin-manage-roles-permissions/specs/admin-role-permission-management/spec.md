## ADDED Requirements

### Requirement: View roles and permissions dashboard
The system SHALL provide an admin dashboard page listing all Keystone roles and all Keystone permissions, accessible only to users holding both the `view-roles` and `view-permissions` permissions.

#### Scenario: Admin with view permissions loads the page
- **WHEN** a user with `view-roles` and `view-permissions` requests `GET /admin/roles-permissions`
- **THEN** the system returns the page listing every role (name, title, description, assigned permission count) and every permission (name, title, description)

#### Scenario: Admin without view permissions is denied
- **WHEN** a user lacking `view-roles` or `view-permissions` requests `GET /admin/roles-permissions`
- **THEN** the system responds with a 403 authorization failure and does not return role or permission data

### Requirement: Create a role
The system SHALL allow a user holding `create-roles` to create a new role with a unique name and optional title/description.

#### Scenario: Successful role creation
- **WHEN** a user with `create-roles` submits a new role with a unique `name`
- **THEN** the system creates the role and it appears in the roles list

#### Scenario: Duplicate role name rejected
- **WHEN** a user with `create-roles` submits a role `name` that already exists
- **THEN** the system rejects the request with a validation error and creates no role

#### Scenario: Unauthorized user cannot create a role
- **WHEN** a user lacking `create-roles` submits a request to create a role
- **THEN** the system responds with a 403 authorization failure and creates no role

### Requirement: Edit a role
The system SHALL allow a user holding `edit-roles` to update an existing role's `title` and `description`. The role's `name` SHALL NOT be editable after creation.

#### Scenario: Successful role update
- **WHEN** a user with `edit-roles` updates the `title` or `description` of an existing role
- **THEN** the system saves the change and reflects it in the roles list

#### Scenario: Unauthorized user cannot edit a role
- **WHEN** a user lacking `edit-roles` submits a request to update a role
- **THEN** the system responds with a 403 authorization failure and leaves the role unchanged

### Requirement: Delete a role
The system SHALL allow a user holding `delete-roles` to delete a role, but SHALL block deletion of a role that is currently assigned to any user.

#### Scenario: Successful role deletion
- **WHEN** a user with `delete-roles` deletes a role that has no users assigned to it
- **THEN** the system deletes the role and it no longer appears in the roles list

#### Scenario: Deletion blocked when role is in use
- **WHEN** a user with `delete-roles` attempts to delete a role that has one or more users assigned to it
- **THEN** the system rejects the deletion with an error explaining the role is in use and does not delete the role

#### Scenario: Unauthorized user cannot delete a role
- **WHEN** a user lacking `delete-roles` submits a request to delete a role
- **THEN** the system responds with a 403 authorization failure and does not delete the role

### Requirement: Create a permission
The system SHALL allow a user holding `create-permissions` to create a new permission with a unique name and optional title/description.

#### Scenario: Successful permission creation
- **WHEN** a user with `create-permissions` submits a new permission with a unique `name`
- **THEN** the system creates the permission and it appears in the permissions list

#### Scenario: Duplicate permission name rejected
- **WHEN** a user with `create-permissions` submits a permission `name` that already exists
- **THEN** the system rejects the request with a validation error and creates no permission

#### Scenario: Unauthorized user cannot create a permission
- **WHEN** a user lacking `create-permissions` submits a request to create a permission
- **THEN** the system responds with a 403 authorization failure and creates no permission

### Requirement: Edit a permission
The system SHALL allow a user holding `edit-permissions` to update an existing permission's `title` and `description`. The permission's `name` SHALL NOT be editable after creation.

#### Scenario: Successful permission update
- **WHEN** a user with `edit-permissions` updates the `title` or `description` of an existing permission
- **THEN** the system saves the change and reflects it in the permissions list

#### Scenario: Unauthorized user cannot edit a permission
- **WHEN** a user lacking `edit-permissions` submits a request to update a permission
- **THEN** the system responds with a 403 authorization failure and leaves the permission unchanged

### Requirement: Delete a permission
The system SHALL allow a user holding `delete-permissions` to delete a permission, but SHALL block deletion of a permission that is currently assigned to any role.

#### Scenario: Successful permission deletion
- **WHEN** a user with `delete-permissions` deletes a permission that is not assigned to any role
- **THEN** the system deletes the permission and it no longer appears in the permissions list

#### Scenario: Deletion blocked when permission is in use
- **WHEN** a user with `delete-permissions` attempts to delete a permission that is assigned to one or more roles
- **THEN** the system rejects the deletion with an error explaining the permission is in use and does not delete the permission

#### Scenario: Unauthorized user cannot delete a permission
- **WHEN** a user lacking `delete-permissions` submits a request to delete a permission
- **THEN** the system responds with a 403 authorization failure and does not delete the permission

### Requirement: Assign permissions to a role
The system SHALL allow a user holding `assign-permissions` to set the full list of permissions assigned to a role, replacing the role's previous permission set.

#### Scenario: Successful permission assignment
- **WHEN** a user with `assign-permissions` submits a new set of permission names for a role
- **THEN** the system syncs the role's permissions to exactly that set

#### Scenario: Unauthorized user cannot assign permissions
- **WHEN** a user lacking `assign-permissions` submits a request to change a role's permission set
- **THEN** the system responds with a 403 authorization failure and leaves the role's permissions unchanged
