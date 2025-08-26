-- Insert 3 sample freelancers
-- Make sure the user_id values exist in your users table first

-- Freelancer 1: Designer
INSERT INTO freelancers (user_id, skillset, availability, status, approved_at, avatar_url) 
VALUES (
    1, 
    'UI/UX Design, Graphic Design, Adobe Creative Suite, Figma, Prototyping, User Research',
    1, 
    'approved', 
    NOW(), 
    'uploads/avatars/designer_avatar.jpg'
);

-- Freelancer 2: Developer
INSERT INTO freelancers (user_id, skillset, availability, status, approved_at, avatar_url) 
VALUES (
    2, 
    'Full Stack Development, PHP, JavaScript, React, Node.js, MySQL, API Development, Git',
    1, 
    'approved', 
    NOW(), 
    'uploads/avatars/developer_avatar.jpg'
);

-- Freelancer 3: Project Manager
INSERT INTO freelancers (user_id, skillset, availability, status, approved_at, avatar_url) 
VALUES (
    3, 
    'Project Management, Agile/Scrum, Team Leadership, Client Communication, Risk Management, Budget Planning',
    1, 
    'approved', 
    NOW(), 
    'uploads/avatars/pm_avatar.jpg'
);

-- Alternative: If you want to create freelancers without specific user_id (assuming they will be linked later)
-- Uncomment the lines below if you want to create freelancers without user_id

/*
INSERT INTO freelancers (skillset, availability, status, approved_at, avatar_url) 
VALUES (
    'UI/UX Design, Graphic Design, Adobe Creative Suite, Figma, Prototyping, User Research',
    1, 
    'approved', 
    NOW(), 
    'uploads/avatars/designer_avatar.jpg'
);

INSERT INTO freelancers (skillset, availability, status, approved_at, avatar_url) 
VALUES (
    'Full Stack Development, PHP, JavaScript, React, Node.js, MySQL, API Development, Git',
    1, 
    'approved', 
    NOW(), 
    'uploads/avatars/developer_avatar.jpg'
);

INSERT INTO freelancers (skillset, availability, status, approved_at, avatar_url) 
VALUES (
    'Project Management, Agile/Scrum, Team Leadership, Client Communication, Risk Management, Budget Planning',
    1, 
    'approved', 
    NOW(), 
    'uploads/avatars/pm_avatar.jpg'
);
*/
