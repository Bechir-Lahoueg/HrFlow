# Skill: Recruitment Domain Intelligence

## 1. The Application Pipeline
The standard workflow for applications is:
1. `PENDING`: New application.
2. `REVIEWING`: HR is looking at the CV.
3. `INTERVIEW`: Candidate is being evaluated.
4. `OFFER`: Decision made to hire.
5. `HIRED`: Candidate accepted.
6. `REJECTED`: Candidate not suitable.

## 2. Interview Scoring
- Scale: 0 to 10.
- `PASSED`: Score ≥ 6.
- `FAILED`: Score < 6.
When asked for "top candidates," prioritize those with PASSED results and high scores.

## 3. Scheduling Buffer
- ALWAYS allow for a 60-minute buffer between interviews to avoid overlap and burnout.
- Check current schedules before proposing a slot.

## 4. Automation Logic
- When a candidate is moved to `HIRED`, their other active applications for DIFFERENT jobs should be reviewed (and potentially rejected if they can only hold one position).
- Soft delete: Data is never truly deleted, just marked as `isDeleted`.

## 5. Job Categories
- Support: IT, HR, Sales, Marketing, Finance.
- Employment Types: CDI, CDD, Stage, Freelance.
- Salary: Always handle with `salaryMin` and `salaryMax` ranges.
