# Prompt: Generate 1500 Rows of Realistic Dummy Data for Gym Membership System

## Objective
Create a Python script or SQL INSERT statements that generate 1500 realistic rows of dummy data for a gym membership system database that includes tables for users, membership_plans, subscriptions, and payments.

## Database Schema Context

### Table 1: membership_plans
- **id**: INT AUTO_INCREMENT PRIMARY KEY
- **name**: VARCHAR(50) - Examples: 'Basic Plan', 'Premium Plan', 'VIP Plan'
- **price**: DECIMAL(10, 2) - Price in currency (e.g., 30.00, 50.00, 550.00)
- **duration_days**: INT - Membership duration (e.g., 30 days, 365 days)
- **Expected data**: 3-5 rows with realistic fitness plan pricing

### Table 2: users
- **id**: INT AUTO_INCREMENT PRIMARY KEY
- **name**: VARCHAR(100) - Full names (realistic Malaysian or diverse names)
- **email**: VARCHAR(100) UNIQUE - Valid email format (firstname.lastname@domain.com pattern)
- **password**: VARCHAR(255) - Pre-hashed passwords (use bcrypt format: `$2y$10$...` - same hash for all for testing)
- **phone**: VARCHAR(20) - Malaysian phone numbers format (e.g., +60173449360, 0173449360)
- **role**: ENUM('admin', 'member') - Mostly 'member', 1 admin
- **loyalty_points**: INT - Range 0-500 loyalty points
- **created_at**: TIMESTAMP - Realistic dates spread over 2024 (Jan-Dec)
- **Total rows needed**: ~1200 member records + 1 admin record

### Table 3: subscriptions
- **id**: INT AUTO_INCREMENT PRIMARY KEY
- **user_id**: INT - Foreign key referencing users.id
- **plan_id**: INT - Foreign key referencing membership_plans.id
- **start_date**: DATE - Subscription start date
- **end_date**: DATE - Subscription end date (calculated from start_date + plan duration)
- **status**: ENUM('active', 'expired', 'pending') - Realistic distribution:
  - ~70% 'active' (recent subscriptions)
  - ~25% 'expired' (older subscriptions)
  - ~5% 'pending' (very recent, not activated yet)
- **Total rows needed**: ~1200 rows (1 subscription per member, some members may have multiple)

### Table 4: payments
- **id**: INT AUTO_INCREMENT PRIMARY KEY
- **user_id**: INT - Foreign key referencing users.id
- **amount**: DECIMAL(10, 2) - Payment amount matching membership_plans.price
- **payment_date**: TIMESTAMP - When payment was made
- **payment_method**: VARCHAR(50) - Default 'Online Banking' (can vary)
- **Total rows needed**: ~1200 rows (multiple payments per user over time)

## Data Generation Requirements

### Names & Contacts
- Use realistic Malaysian names (Mix of Malay, Chinese, Indian names)
- Generate unique valid email addresses in pattern: firstname.lastname{number}@mail.com
- Generate valid Malaysian phone numbers (01x-xxxxxxxx format or +6017xxxxxxx)
- Ensure name+email combination is unique

### Dates
- **Join dates**: Spread across 2024 (Jan 1 - Dec 31)
- **Subscription dates**: Start dates between join_date and recent dates
- **Payment dates**: Align with subscription periods
- **Realistic pattern**: Earlier members more likely to be 'active', newer members more likely 'pending'

### Pricing & Plans
Create 3-5 membership plans with realistic gym pricing:
- Basic/Standard Plan: $30-40/month (30 days)
- Premium Plan: $50-70/month (30 days)
- VIP/Elite Plan: $500-600/year (365 days)

### Phone Numbers
- Generate realistic Malaysian format
- Examples: 0173449360, 0157245677, +60173449360
- Ensure variety in area codes (01, 02, 03, 04, 05, 06, 07, 08, 09)

### Status Distribution
- Users: All role='member' except 1 admin
- Subscriptions:
  - 70% 'active' - recent start dates, end_date in future
  - 25% 'expired' - older start dates, end_date in past
  - 5% 'pending' - very recent start dates, status='pending'
- Loyalty Points: 0-500, realistic distribution (newer members: lower, older: higher)

## Output Format

Provide one of the following:
1. **SQL INSERT Statements** - Ready to run in MySQL/MariaDB
   - Format: Multiple INSERT INTO statements with 50-100 rows per statement
   - Include INSERT INTO membership_plans, users, subscriptions, and payments
   - Ensure FOREIGN KEY constraints are satisfied
   
2. **Python Script** - Using faker library or similar
   - Generate realistic data using libraries like `faker`, `random`
   - Output to SQL file or CSV format
   - Include comments explaining the data generation logic
   - Make it parameterized (easy to adjust row counts)

## Constraints & Rules
- **NO DUPLICATES**: All emails must be unique
- **Data Integrity**: 
  - Every subscription must reference valid user_id and plan_id
  - Every payment must reference valid user_id
  - Subscription end_date must be start_date + plan.duration_days
- **Realistic distribution**: Don't make all data identical; vary ages, join dates, statuses
- **Password**: Use same bcrypt hash for all: `$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi`
- **Created_at timestamps**: Should align with subscription start dates (member created when they join)

## Example Sample Row

**User:**
```sql
INSERT INTO users (name, email, password, phone, role, loyalty_points, created_at) 
VALUES ('Nazri Latif', 'nazri.latif1@mail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0173449360', 'member', 100, '2024-01-15 08:30:00');
```

**Subscription:**
```sql
INSERT INTO subscriptions (user_id, plan_id, start_date, end_date, status) 
VALUES (1, 2, '2024-01-15', '2024-02-14', 'active');
```

**Payment:**
```sql
INSERT INTO payments (user_id, amount, payment_date, payment_method) 
VALUES (1, 50.00, '2024-01-15 08:35:00', 'Online Banking');
```

## Delivery Checklist
- [ ] 1500 total dummy records generated
- [ ] ~1200 member user records
- [ ] 1 admin user record
- [ ] 3-5 membership plan records
- [ ] ~1200 subscription records
- [ ] ~1200-1500 payment records (some members may have multiple payments)
- [ ] All data is realistic and follows Malaysian conventions
- [ ] No duplicate emails or invalid phone numbers
- [ ] All foreign key relationships are valid
- [ ] SQL file ready to execute or Python script ready to run
- [ ] Data distribution is realistic (70% active, 25% expired, 5% pending)
