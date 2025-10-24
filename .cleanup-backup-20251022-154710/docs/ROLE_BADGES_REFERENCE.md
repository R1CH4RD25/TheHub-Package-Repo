# School District Roles - Quick Reference

## Role Hierarchy & Badge Colors

### Executive Tier
**Superintendent** - `role_superintendent`
- Badge: Dark Navy #1E3A8A / White (Bold)
- Description: District superintendent with executive access
- Permissions: View fuel records, manage sections, manage modules
- Use Cases: District leadership, executive officers, central office administrators

**Super Admin** - `role_super_admin`
- Badge: Light Red #FEE2E2 / Dark Red #991B1B (Bold)
- Description: System administrator with full access
- Permissions: All permissions, system configuration
- Use Cases: IT administrators, platform managers

### Administrative Tier
**Admin** - `role_admin`
- Badge: Light Pink #FCE7F3 / Dark Pink #9F1239
- Description: Administrator with broad access
- Permissions: Manage users, view fuel records, manage sections
- Use Cases: Business managers, operations directors

**Manager** - `role_manager`
- Badge: Light Purple #DDD6FE / Dark Purple #5B21B6
- Description: Department or area manager
- Permissions: Team oversight, reporting access
- Use Cases: Department heads, area supervisors

**Principal** - `role_principal`
- Badge: Light Blue #DBEAFE / Navy #1E3A8A
- Description: School principal with elevated access
- Permissions: View fuel records, campus-level management
- Use Cases: Campus administrators, assistant principals, deans

### Operations Tier
**Maintenance Director** - `role_maintenance_director`
- Badge: Light Orange #FED7AA / Dark Orange #9A3412
- Description: Maintenance department director
- Permissions: Manage vehicles, manage fuel records, team management
- Use Cases: Facilities director, maintenance supervisor

**IT Support** - `role_it_support`
- Badge: Light Gray #F3F4F6 / Dark Gray #374151
- Description: Technology support staff
- Permissions: Manage modules, technical configuration
- Use Cases: Technology coordinators, helpdesk staff, network administrators

### Support Services Tier
**Secretary** - `role_secretary`
- Badge: Light Peach #FFF7ED / Brown #9A3412
- Description: Office secretary with administrative support access
- Permissions: Basic administrative functions
- Use Cases: Front office staff, administrative assistants, receptionists

**Librarian** - `role_librarian`
- Badge: Light Green #F0FDF4 / Dark Green #166534
- Description: Library staff with resource management access
- Permissions: Resource management, student interaction
- Use Cases: Media specialists, library coordinators, resource managers

**Counselor** - `role_counselor`
- Badge: Light Purple #F5F3FF / Purple #6B21A8
- Description: School counselor with student access
- Permissions: Student services, counseling records
- Use Cases: Guidance counselors, social workers, student advisors

### Field Tier
**Maintenance** - `role_maintenance`
- Badge: Light Yellow #FEF3C7 / Brown #92400E
- Description: Maintenance worker with field access
- Permissions: Enter fuel records, view assigned vehicles
- Use Cases: Maintenance technicians, custodians, groundskeepers

**Teacher** - `role_teacher`
- Badge: Sky Blue #EFF6FF / Blue #1E40AF
- Description: Classroom teacher with basic access
- Permissions: Basic platform access, classroom resources
- Use Cases: Faculty members, classroom instructors, coaches

**Staff** - `role_staff`
- Badge: Light Blue #E0E7FF / Indigo #3730A3
- Description: General staff member with basic access
- Permissions: View-only access to assigned areas
- Use Cases: Support staff, paraprofessionals, volunteers

## Role Selection Guide

### When to assign each role:

**Superintendent** → District-level executives who need system-wide oversight
**Super Admin** → Technical administrators who configure the platform
**Admin** → Business/operations managers who handle day-to-day administration
**Manager** → Department heads who oversee specific areas or teams
**Principal** → Campus leaders who manage school buildings
**Maintenance Director** → Facilities leaders who oversee maintenance operations
**IT Support** → Technology staff who provide technical assistance
**Secretary** → Office professionals who handle administrative tasks
**Librarian** → Media specialists who manage library resources
**Counselor** → Student services professionals who support student needs
**Maintenance** → Field workers who perform maintenance and repair tasks
**Teacher** → Instructional staff who work directly with students
**Staff** → General employees with limited access needs

## Permission Matrix

| Role | View Fuel | Manage Fuel | Manage Vehicles | Manage Users | Manage Sections | Manage Modules |
|------|-----------|-------------|-----------------|--------------|-----------------|----------------|
| Superintendent | ✓ | - | - | - | ✓ | ✓ |
| Super Admin | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Admin | ✓ | ✓ | ✓ | ✓ | ✓ | - |
| Manager | ✓ | ✓ | ✓ | - | - | - |
| Principal | ✓ | - | - | - | - | - |
| Maint. Director | ✓ | ✓ | ✓ | - | - | - |
| IT Support | - | - | - | - | - | ✓ |
| Secretary | - | - | - | - | - | - |
| Librarian | - | - | - | - | - | - |
| Counselor | - | - | - | - | - | - |
| Maintenance | ✓ | ✓ | - | - | - | - |
| Teacher | - | - | - | - | - | - |
| Staff | - | - | - | - | - | - |

## Badge Color Themes

All badge colors are designed to:
- Meet WCAG AA contrast standards
- Work well in both light and dark mode themes
- Be visually distinct from each other
- Follow professional color psychology:
  - **Blues** → Trust, stability (Teacher, Principal, Counselor)
  - **Purples** → Leadership, wisdom (Manager, Counselor)
  - **Greens** → Growth, service (Librarian)
  - **Warm tones** → Support, care (Secretary, Maintenance)
  - **Grays** → Technical, neutral (IT Support, Staff)
  - **Bold backgrounds** → Executive authority (Superintendent, Super Admin)

## CSS Variable Usage

Each role has two CSS variables:
```css
--role-{role_name}-bg: background color
--role-{role_name}-text: text color
```

Example:
```css
--role-teacher-bg: #EFF6FF;
--role-teacher-text: #1E40AF;
```

These variables are automatically applied when themes are activated, ensuring consistent branding across the platform.

## Adding More Roles (Future)

To add a new role:
1. Add badge colors to `site_settings` table
2. Add CSS class to `admin.css`
3. Add CSS variables to `SiteSettings.php::getCSSVariables()`
4. Add filter keys to `Theme.php::filterThemeSettings()`
5. Optionally: Update role enum in database schema

Suggested future roles:
- Assistant Principal
- Department Head
- Athletic Director
- Nurse
- Food Service Director
- Transportation Director
- Curriculum Coordinator
- Student (limited portal access)
- Parent (parent portal)
- Board Member (read-only executive)

---

*Last Updated: October 22, 2025*
*Version: 2.0*
