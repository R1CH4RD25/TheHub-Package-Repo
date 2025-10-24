# Migration Guide - Modernizing Existing Code

This guide helps you update existing Hub code to use the new modern frontend libraries.

## Quick Replacements

### Alerts & Notifications

**Before:**
```javascript
alert('User saved successfully');
```

**After:**
```javascript
TheHub.notify('User saved successfully', 'success');
```

### Confirmations

**Before:**
```javascript
if (confirm('Are you sure you want to delete this?')) {
    deleteItem();
}
```

**After:**
```javascript
if (await TheHub.confirm('Delete Item?', 'This action cannot be undone')) {
    deleteItem();
}
```

### AJAX Requests

**Before:**
```javascript
fetch('/api/users', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify(data)
})
.then(response => response.json())
.then(data => {
    alert('Success!');
})
.catch(error => {
    alert('Error: ' + error.message);
});
```

**After:**
```javascript
try {
    const response = await axios.post('/api/users', data);
    TheHub.notify('Success!', 'success');
} catch (error) {
    TheHub.notify('Error: ' + error.message, 'error');
}
```

### Form Validation Messages

**Before:**
```javascript
function showMessage(message, type) {
    const messageDiv = document.createElement('div');
    messageDiv.className = 'alert alert-' + type;
    messageDiv.textContent = message;
    document.body.appendChild(messageDiv);
    setTimeout(() => messageDiv.remove(), 3000);
}
```

**After:**
```javascript
// Just use TheHub.notify() - it's already better!
TheHub.notify(message, type); // types: success, error, warning, info
```

### Loading States

**Before:**
```javascript
const loadingDiv = document.createElement('div');
loadingDiv.innerHTML = 'Loading...';
document.body.appendChild(loadingDiv);

// Do async work
await fetchData();

loadingDiv.remove();
```

**After:**
```javascript
TheHub.showLoading('Loading...');
await fetchData();
TheHub.closeLoading();
```

### Custom Modals

**Before:**
```javascript
const modalHTML = `
    <div class="modal-overlay">
        <div class="modal-content">
            <h3>Title</h3>
            <p>Message</p>
            <button onclick="closeModal()">OK</button>
        </div>
    </div>
`;
document.body.insertAdjacentHTML('beforeend', modalHTML);
```

**After:**
```javascript
Swal.fire({
    title: 'Title',
    text: 'Message',
    icon: 'info',
    confirmButtonText: 'OK'
});
```

### Date Formatting

**Before:**
```javascript
const date = new Date();
const formatted = date.getFullYear() + '-' + 
    String(date.getMonth() + 1).padStart(2, '0') + '-' + 
    String(date.getDate()).padStart(2, '0');
```

**After:**
```javascript
const formatted = dayjs().format('YYYY-MM-DD');
// Or relative time: dayjs('2024-01-01').fromNow() → "10 months ago"
```

### Input Validation

**Before:**
```javascript
const input = document.getElementById('email');
if (!input.value.includes('@')) {
    alert('Invalid email');
    return;
}
```

**After:**
```javascript
const input = document.getElementById('email');
if (!input.value.includes('@')) {
    TheHub.notify('Invalid email address', 'error');
    return;
}
```

## Enhanced Features

### Add Date Picker to Input

**Before:**
```html
<input type="text" id="date" placeholder="YYYY-MM-DD">
<script>
    // Manual date validation...
</script>
```

**After:**
```html
<input type="text" id="date" placeholder="Select date">
<script>
    flatpickr('#date', {
        enableTime: true,
        dateFormat: 'Y-m-d H:i',
        minDate: 'today'
    });
</script>
```

### Add Autocomplete to Select

**Before:**
```html
<select id="user">
    <option value="1">John Doe</option>
    <option value="2">Jane Smith</option>
    <!-- 100+ more options -->
</select>
```

**After:**
```html
<select id="user">
    <option value="1">John Doe</option>
    <option value="2">Jane Smith</option>
</select>
<script>
    new TomSelect('#user', {
        create: false,
        sortField: 'text',
        plugins: ['remove_button']
    });
</script>
```

### Add Tooltips

**Before:**
```html
<button title="Click to edit">
    <i class="icon-edit"></i>
</button>
```

**After:**
```html
<button data-bs-toggle="tooltip" title="Click to edit user profile">
    <i class="bi bi-pencil"></i>
</button>
<!-- Tooltips auto-initialize via TheHub.init() -->
```

### Add Scroll Animations

**Before:**
```html
<div class="card">Content</div>
<script>
    // Complex Intersection Observer code...
</script>
```

**After:**
```html
<div class="card" data-aos="fade-up" data-aos-duration="800">
    Content
</div>
<!-- Animations auto-initialize via AOS.init() -->
```

### Create Charts

**Before:**
```html
<canvas id="chart"></canvas>
<script>
    // 50+ lines of custom charting code...
</script>
```

**After:**
```html
<canvas id="chart"></canvas>
<script>
    new Chart(document.getElementById('chart'), {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'Sales',
                data: [12, 19, 15, 25, 22, 30]
            }]
        }
    });
</script>
```

### Reactive UI (instead of manual DOM manipulation)

**Before:**
```html
<div>
    <button onclick="increment()">+</button>
    <span id="count">0</span>
    <button onclick="decrement()">-</button>
</div>
<script>
    let count = 0;
    function increment() {
        count++;
        document.getElementById('count').textContent = count;
    }
    function decrement() {
        count--;
        document.getElementById('count').textContent = count;
    }
</script>
```

**After:**
```html
<div x-data="{ count: 0 }">
    <button @click="count++">+</button>
    <span x-text="count"></span>
    <button @click="count--">-</button>
</div>
<!-- No JavaScript needed! -->
```

### Dynamic Content Updates

**Before:**
```javascript
async function loadContent() {
    const response = await fetch('/api/content');
    const html = await response.text();
    document.getElementById('content').innerHTML = html;
}
```

**After:**
```html
<button hx-get="/api/content" hx-target="#content">Load Content</button>
<div id="content"></div>
<!-- HTMX handles the AJAX automatically -->
```

## Icon Migration

### Replace Old Icon Classes

**Before:**
```html
<i class="fa fa-user"></i>          <!-- Font Awesome -->
<i class="glyphicon glyphicon-home"></i>  <!-- Glyphicons -->
<span class="icon-save"></span>      <!-- Custom icons -->
```

**After:**
```html
<i class="bi bi-person"></i>         <!-- Bootstrap Icons -->
<i class="bi bi-house"></i>
<i class="bi bi-save"></i>
```

Browse all icons: https://icons.getbootstrap.com/

## Button Styling

**Before:**
```html
<button class="btn-primary">Save</button>
<button class="btn-danger">Delete</button>
```

**After:**
```html
<button class="btn btn-primary">
    <i class="bi bi-save"></i> Save
</button>
<button class="btn btn-danger">
    <i class="bi bi-trash"></i> Delete
</button>
```

## Utility Classes (Bootstrap 5)

Bootstrap 5 provides comprehensive utility classes:

```html
<!-- Spacing -->
<div class="mt-3 mb-4 p-3">Margin top 3, bottom 4, padding 3</div>

<!-- Display -->
<div class="d-flex justify-content-between align-items-center">
    <span>Left</span>
    <span>Right</span>
</div>

<!-- Colors -->
<div class="text-primary bg-light">Colored text and background</div>

<!-- Borders -->
<div class="border border-primary rounded">Bordered box</div>

<!-- Shadows -->
<div class="shadow-sm">Subtle shadow</div>
```

## Common Patterns

### Delete Confirmation

**Before:**
```javascript
function deleteUser(userId) {
    if (confirm('Delete this user?')) {
        fetch('/api/users/' + userId, { method: 'DELETE' })
            .then(() => alert('Deleted!'))
            .catch(err => alert('Error: ' + err));
    }
}
```

**After:**
```javascript
async function deleteUser(userId) {
    const confirmed = await TheHub.confirm(
        'Delete User?',
        'This action cannot be undone',
        'Delete'
    );
    
    if (confirmed) {
        try {
            await axios.delete(`/api/users/${userId}`);
            TheHub.notify('User deleted successfully', 'success');
            // Optionally reload the page or remove the row
        } catch (error) {
            TheHub.notify('Failed to delete user', 'error');
        }
    }
}
```

### Form Submission

**Before:**
```javascript
document.getElementById('userForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    
    fetch('/api/users', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        alert('User saved!');
        this.reset();
    })
    .catch(error => alert('Error: ' + error.message));
});
```

**After:**
```javascript
document.getElementById('userForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    
    TheHub.showLoading('Saving user...');
    
    try {
        const response = await axios.post('/api/users', data);
        TheHub.closeLoading();
        TheHub.notify('User saved successfully!', 'success');
        this.reset();
    } catch (error) {
        TheHub.closeLoading();
        TheHub.notify('Failed to save user: ' + error.message, 'error');
    }
});
```

## Testing Migration

After updating code, test:

1. **Notifications work**: `TheHub.notify('Test', 'success')`
2. **Confirmations work**: `await TheHub.confirm('Test?', 'Message')`
3. **AJAX works**: Check Network tab for proper headers (CSRF token)
4. **Icons display**: All `bi-*` icons should render
5. **Tooltips appear**: Hover over elements with `data-bs-toggle="tooltip"`
6. **Charts render**: Canvas elements should show visualizations
7. **No console errors**: Check browser console for issues

## Gradual Migration Strategy

You don't have to migrate everything at once:

1. **Phase 1**: Start with new features using modern libraries
2. **Phase 2**: Replace `alert()` and `confirm()` with TheHub methods
3. **Phase 3**: Update AJAX calls to use Axios
4. **Phase 4**: Add tooltips and animations to existing UI
5. **Phase 5**: Enhance forms with date pickers and autocomplete
6. **Phase 6**: Replace custom modals with SweetAlert2

## Need Help?

- View live examples: `/frontend-demo.html`
- Check library status: `/test-modern-libs.php`
- Read full docs: `docs/FRONTEND_LIBRARIES.md`
- Quick reference: `./frontend-quickref.sh`

## Pro Tips

1. **Use TheHub.notify() everywhere** - More professional than alert()
2. **Use Axios for all API calls** - CSRF protection is automatic
3. **Add data-aos to key sections** - Instant professional polish
4. **Use Bootstrap utilities** - Faster than custom CSS
5. **Use Alpine.js for reactive UI** - Simpler than writing vanilla JS
6. **Browse Bootstrap Icons** - Find the perfect icon for every action

---

**Remember**: All old code continues to work! This is about enhancing, not breaking existing functionality.
