# Exception Handling Best Practices

## Overview

This document explains the best practices for exception handling in a Laravel application with a layered architecture (Repository → Service → Controller).

## Current Architecture

```
Controller (HTTP Layer)
    ↓ calls
Service (Business Logic Layer)
    ↓ calls
Repository (Data Access Layer)
    ↓ queries
Database
```

## Best Practice: Exception Handling Strategy

### ✅ **Recommended Approach**

**1. Repositories: NO try-catch (Let exceptions bubble up)**
- Laravel's Eloquent exceptions are already descriptive
- Database exceptions should bubble up naturally
- Reduces code duplication and complexity

**2. Services: Optional try-catch (Only for business logic)**
- Catch exceptions only when you need to transform them for business rules
- Usually let exceptions bubble up to controllers

**3. Controllers: try-catch (For HTTP response conversion)**
- Catch exceptions and convert to JSON responses
- Handle different exception types appropriately
- Return consistent error formats

**4. Global Exception Handler: Handle common cases**
- Use Laravel's exception handler for consistent API error responses
- Handle ValidationException, ModelNotFoundException, etc.
- Reduces boilerplate in controllers

### ❌ **Anti-Pattern: Double try-catch**

**Don't do this:**
```php
// Repository
public function findById($id) {
    try {
        return Model::find($id);
    } catch (\Exception $e) {
        throw new \RuntimeException('Failed...'); // Unnecessary wrapping
    }
}

// Controller
public function show($id) {
    try {
        $model = $this->service->findById($id);
    } catch (\Exception $e) {
        return $this->sendError(...); // Redundant
    }
}
```

### ✅ **Best Practice:**

```php
// Repository - Let exceptions bubble naturally
public function findById($id) {
    return Model::findOrFail($id); // Throws ModelNotFoundException
}

// Controller - Catch and convert to HTTP response
public function show($id) {
    try {
        $model = $this->service->findById($id);
        return $this->sendResponse($model, 'Success');
    } catch (ModelNotFoundException $e) {
        return $this->sendError('Resource not found', [], 404);
    } catch (\Exception $e) {
        return $this->sendError('An error occurred', [], 500);
    }
}

// OR use Global Exception Handler (Even Better!)
// Handler.php automatically converts ModelNotFoundException to 404 JSON
public function show($id) {
    $model = $this->service->findById($id); // Exception handled globally
    return $this->sendResponse($model, 'Success');
}
```

## Exception Types in Laravel

### Common Exceptions:

1. **ValidationException** → 422 (Unprocessable Entity)
   - Thrown by Form Requests
   - Handled automatically by Laravel

2. **ModelNotFoundException** → 404 (Not Found)
   - Thrown by `findOrFail()`, `firstOrFail()`
   - Can be handled globally

3. **QueryException** → 500 (Database Error)
   - Thrown by database operations
   - Should be logged but not exposed in production

4. **AuthorizationException** → 403 (Forbidden)
   - Thrown by Policies
   - Handled automatically by Laravel

5. **AuthenticationException** → 401 (Unauthorized)
   - Thrown by auth middleware
   - Handled automatically by Laravel

## Implementation Strategy

### Option 1: Global Exception Handler (Recommended)

**Pros:**
- Consistent error responses across all controllers
- Less boilerplate code
- Centralized error handling logic
- Easier to maintain

**Cons:**
- Less granular control per endpoint
- Need to handle edge cases globally

**Use when:**
- You want consistent API error responses
- Most exceptions should be handled the same way
- You have many controllers

### Option 2: Controller-Level try-catch

**Pros:**
- Fine-grained control per endpoint
- Can customize error messages per action
- Easy to understand flow

**Cons:**
- More boilerplate code
- Inconsistent error responses if not careful
- More maintenance

**Use when:**
- Different endpoints need different error handling
- You need custom error messages per action
- You have few controllers

### Option 3: Hybrid Approach (Best of Both)

**Use Global Handler for:**
- Common exceptions (ModelNotFoundException, ValidationException)
- Standard error formats

**Use Controller try-catch for:**
- Business logic exceptions
- Custom error messages
- Special cases

## Migration Path

1. **Step 1**: Create/Update Global Exception Handler
2. **Step 2**: Remove try-catch from Repositories
3. **Step 3**: Keep try-catch in Controllers for business logic exceptions
4. **Step 4**: Test and verify error responses

## Example: Refactored Repository

### Before (with try-catch):
```php
public function findById($id) {
    try {
        return Model::find($id);
    } catch (\Exception $e) {
        throw new \RuntimeException('Failed to find: ' . $e->getMessage(), 0, $e);
    }
}
```

### After (without try-catch):
```php
public function findById($id) {
    return Model::findOrFail($id); // Throws ModelNotFoundException if not found
}
```

## Benefits of Removing Repository try-catch

1. **Less Code**: Removes unnecessary boilerplate
2. **Better Exceptions**: Laravel's exceptions are more descriptive
3. **Easier Debugging**: Original exception stack trace preserved
4. **Consistent**: Follows Laravel conventions
5. **Maintainable**: Less code to maintain

## When to Keep try-catch in Repositories

Keep try-catch in repositories ONLY when:
- You need to log specific database errors
- You need to transform database exceptions to domain exceptions
- You're wrapping external API calls (not Eloquent queries)

## Conclusion

**Recommended Pattern:**
- ✅ **Repositories**: No try-catch (let exceptions bubble)
- ✅ **Services**: Optional try-catch (business logic only)
- ✅ **Controllers**: try-catch for business exceptions
- ✅ **Global Handler**: Handle common exceptions (ModelNotFoundException, ValidationException, etc.)

This approach reduces code duplication, improves maintainability, and follows Laravel best practices.
