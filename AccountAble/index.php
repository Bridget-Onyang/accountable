<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>AccountAble - Goal Tracking & Accountability System</title>
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    />
    <style>
      :root {
        --primary-color: #2e7d32; /* Deep green */
        --primary-dark: #1b5e20; /* Darker green */
        --primary-light: #4caf50; /* Lighter green */
        --accent-color: #8bc34a; /* Lime green accent */
        --dark-color: #212121; /* Dark gray/black */
        --light-color: #f5f5f5; /* Light gray */
        --text-dark: #333333;
        --text-light: #ffffff;
        --gray-color: #757575;
        --success-color: #4caf50;
        --shadow-sm: 0 2px 5px rgba(0, 0, 0, 0.1);
        --shadow-md: 0 4px 10px rgba(0, 0, 0, 0.15);
        --shadow-lg: 0 8px 20px rgba(0, 0, 0, 0.2);
        --transition: all 0.3s ease;
      }

      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: "Segoe UI", "Roboto", "Helvetica Neue", sans-serif;
      }

      body {
        background-color: var(--light-color);
        color: var(--text-dark);
        line-height: 1.6;
        overflow-x: hidden;
      }

      /* Header Styles */
      .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem 5%;
        background-color: var(--dark-color);
        position: sticky;
        top: 0;
        z-index: 1000;
        box-shadow: var(--shadow-sm);
      }

      .logo {
        display: flex;
        align-items: center;
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--text-light);
      }

      .logo i {
        margin-right: 0.75rem;
        color: var(--accent-color);
        font-size: 1.5rem;
      }

      .nav-links {
        display: flex;
        gap: 2rem;
      }

      .nav-links a {
        color: var(--text-light);
        text-decoration: none;
        font-weight: 500;
        transition: var(--transition);
        position: relative;
      }

      .nav-links a:hover {
        color: var(--accent-color);
      }

      .nav-links a::after {
        content: "";
        position: absolute;
        width: 0;
        height: 2px;
        bottom: -5px;
        left: 0;
        background-color: var(--accent-color);
        transition: var(--transition);
      }

      .nav-links a:hover::after {
        width: 100%;
      }

      .auth-buttons {
        display: flex;
        gap: 1rem;
      }

      .btn {
        padding: 0.75rem 1.5rem;
        border-radius: 30px;
        font-weight: 600;
        text-decoration: none;
        transition: var(--transition);
        cursor: pointer;
        border: none;
        font-size: 0.95rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
      }

      .login-btn {
        color: var(--text-light);
        background-color: transparent;
        border: 2px solid var(--primary-light);
      }

      .login-btn:hover {
        background-color: rgba(255, 255, 255, 0.1);
      }

      .signup-btn {
        background-color: var(--primary-color);
        color: var(--text-light);
        border: 2px solid var(--primary-color);
      }

      .signup-btn:hover {
        background-color: var(--primary-dark);
        border-color: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: var(--shadow-sm);
      }

      /* Hero Section */
      .dashboard-hero {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 5rem 5%;
        max-width: 1400px;
        margin: 0 auto;
        min-height: 80vh;
      }

      .hero-content {
        flex: 1;
        padding-right: 3rem;
        animation: fadeInLeft 1s ease;
      }

      .hero-image {
        flex: 1;
        text-align: center;
        position: relative;
        animation: fadeInRight 1s ease;
      }

      .hero-image img {
        max-width: 100%;
        height: auto;
        border-radius: 10px;
        box-shadow: var(--shadow-lg);
      }

      h1 {
        font-size: 3.5rem;
        margin-bottom: 1.5rem;
        color: var(--dark-color);
        line-height: 1.2;
        font-weight: 800;
      }

      h1 span {
        color: var(--primary-color);
      }

      .subtitle {
        font-size: 1.3rem;
        color: var(--gray-color);
        margin-bottom: 2rem;
        max-width: 600px;
        font-weight: 400;
      }

      .cta-buttons {
        display: flex;
        gap: 1.5rem;
        margin-top: 2rem;
      }

      .primary-btn {
        background-color: var(--primary-color);
        color: var(--text-light);
        box-shadow: var(--shadow-sm);
      }

      .primary-btn:hover {
        background-color: var(--primary-dark);
        transform: translateY(-3px);
        box-shadow: var(--shadow-md);
      }

      .secondary-btn {
        background-color: transparent;
        color: var(--primary-color);
        border: 2px solid var(--primary-color);
      }

      .secondary-btn:hover {
        background-color: rgba(46, 125, 50, 0.1);
        transform: translateY(-3px);
        box-shadow: var(--shadow-sm);
      }

      /* Features Section */
      .features {
        padding: 6rem 5%;
        background-color: white;
        text-align: center;
      }

      .section-title {
        font-size: 2.5rem;
        margin-bottom: 1rem;
        color: var(--dark-color);
        position: relative;
        display: inline-block;
      }

      .section-title::after {
        content: "";
        position: absolute;
        width: 60%;
        height: 4px;
        bottom: -10px;
        left: 20%;
        background-color: var(--primary-color);
        border-radius: 2px;
      }

      .section-subtitle {
        font-size: 1.2rem;
        color: var(--gray-color);
        margin-bottom: 4rem;
        max-width: 700px;
        margin-left: auto;
        margin-right: auto;
      }

      .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2.5rem;
        max-width: 1200px;
        margin: 0 auto;
      }

      .feature-card {
        background-color: var(--light-color);
        padding: 2.5rem 2rem;
        border-radius: 10px;
        transition: var(--transition);
        text-align: left;
        border: 1px solid rgba(0, 0, 0, 0.05);
      }

      .feature-card:hover {
        transform: translateY(-10px);
        box-shadow: var(--shadow-lg);
        border-color: var(--primary-light);
      }

      .feature-icon {
        font-size: 2.5rem;
        color: var(--primary-color);
        margin-bottom: 1.5rem;
        background-color: rgba(46, 125, 50, 0.1);
        width: 70px;
        height: 70px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
      }

      .feature-card h3 {
        margin-bottom: 1rem;
        color: var(--dark-color);
        font-size: 1.5rem;
      }

      .feature-card p {
        color: var(--gray-color);
        font-size: 1rem;
        line-height: 1.7;
      }

      /* Testimonials */
      .testimonials {
        padding: 6rem 5%;
        background-color: var(--dark-color);
        text-align: center;
        color: var(--text-light);
      }

      .testimonials .section-title {
        color: var(--text-light);
      }

      .testimonials .section-subtitle {
        color: rgba(255, 255, 255, 0.7);
      }

      .testimonial-container {
        max-width: 1200px;
        margin: 0 auto;
        position: relative;
      }

      .testimonial-slider {
        display: flex;
        overflow-x: auto;
        scroll-snap-type: x mandatory;
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
        margin-bottom: 2rem;
        scrollbar-width: none; /* Firefox */
      }

      .testimonial-slider::-webkit-scrollbar {
        display: none; /* Chrome/Safari */
      }

      .testimonial-card {
        flex: 0 0 100%;
        scroll-snap-align: start;
        background-color: rgba(255, 255, 255, 0.05);
        padding: 3rem;
        border-radius: 10px;
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
        margin-right: 1rem;
        min-height: 350px;
        display: flex;
        flex-direction: column;
        justify-content: center;
      }

      .testimonial-content p {
        font-size: 1.3rem;
        font-style: italic;
        margin-bottom: 2rem;
        color: rgba(255, 255, 255, 0.9);
        line-height: 1.8;
        position: relative;
      }

      .testimonial-content p::before,
      .testimonial-content p::after {
        content: '"';
        font-size: 3rem;
        color: var(--primary-light);
        opacity: 0.3;
        position: absolute;
      }

      .testimonial-content p::before {
        top: -20px;
        left: -15px;
      }

      .testimonial-content p::after {
        bottom: -40px;
        right: -15px;
      }

      .testimonial-author {
        display: flex;
        align-items: center;
        justify-content: center;
      }

      .testimonial-author img {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        margin-right: 1.5rem;
        object-fit: cover;
        border: 3px solid var(--primary-light);
      }

      .author-info h4 {
        color: var(--text-light);
        margin-bottom: 0.3rem;
        font-size: 1.2rem;
      }

      .author-info span {
        color: var(--accent-color);
        font-size: 0.9rem;
      }

      .slider-nav {
        display: flex;
        justify-content: center;
        gap: 1rem;
        margin-top: 2rem;
      }

      .slider-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background-color: rgba(255, 255, 255, 0.3);
        cursor: pointer;
        transition: var(--transition);
      }

      .slider-dot.active {
        background-color: var(--primary-light);
        transform: scale(1.2);
      }

      /* Stats Section */
      .stats {
        padding: 6rem 5%;
        background-color: var(--primary-color);
        color: var(--text-light);
        text-align: center;
      }

      .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 3rem;
        max-width: 1200px;
        margin: 0 auto;
      }

      .stat-item {
        padding: 2rem;
      }

      .stat-number {
        font-size: 3.5rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
      }

      .stat-label {
        font-size: 1.2rem;
        opacity: 0.9;
      }

      /* CTA Section */
      .cta-section {
        padding: 6rem 5%;
        background-color: var(--light-color);
        text-align: center;
      }

      .cta-card {
        max-width: 800px;
        margin: 0 auto;
        background-color: white;
        padding: 4rem;
        border-radius: 10px;
        box-shadow: var(--shadow-lg);
        position: relative;
        overflow: hidden;
      }

      .cta-card::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 8px;
        height: 100%;
        background-color: var(--primary-color);
      }

      .cta-card h2 {
        font-size: 2.2rem;
        margin-bottom: 1.5rem;
        color: var(--dark-color);
      }

      .cta-card p {
        color: var(--gray-color);
        margin-bottom: 2.5rem;
        font-size: 1.1rem;
        line-height: 1.8;
      }

      /* Footer */
      .dashboard-footer {
        background-color: var(--dark-color);
        color: var(--text-light);
        padding: 4rem 5% 2rem;
      }

      .footer-content {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 3rem;
        max-width: 1200px;
        margin: 0 auto 3rem;
      }

      .footer-logo {
        display: flex;
        align-items: center;
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--text-light);
        margin-bottom: 1.5rem;
      }

      .footer-logo i {
        margin-right: 0.75rem;
        color: var(--accent-color);
      }

      .footer-about p {
        color: rgba(255, 255, 255, 0.7);
        margin-bottom: 1.5rem;
        line-height: 1.7;
      }

      .footer-links h3,
      .footer-contact h3 {
        font-size: 1.3rem;
        margin-bottom: 1.5rem;
        color: var(--text-light);
        position: relative;
        padding-bottom: 0.5rem;
      }

      .footer-links h3::after,
      .footer-contact h3::after {
        content: "";
        position: absolute;
        width: 50px;
        height: 3px;
        bottom: 0;
        left: 0;
        background-color: var(--primary-light);
      }

      .footer-links ul {
        list-style: none;
      }

      .footer-links li {
        margin-bottom: 0.8rem;
      }

      .footer-links a {
        color: rgba(255, 255, 255, 0.7);
        text-decoration: none;
        transition: var(--transition);
        display: inline-block;
      }

      .footer-links a:hover {
        color: var(--accent-color);
        transform: translateX(5px);
      }

      .footer-contact p {
        color: rgba(255, 255, 255, 0.7);
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.8rem;
      }

      .footer-contact i {
        color: var(--accent-color);
        width: 20px;
      }

      .footer-social {
        display: flex;
        gap: 1.5rem;
        margin-top: 1.5rem;
      }

      .footer-social a {
        color: var(--text-light);
        background-color: rgba(255, 255, 255, 0.1);
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: var(--transition);
      }

      .footer-social a:hover {
        background-color: var(--primary-light);
        transform: translateY(-3px);
      }

      .footer-bottom {
        text-align: center;
        padding-top: 2rem;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.9rem;
      }

      /* Animations */
      @keyframes fadeInLeft {
        from {
          opacity: 0;
          transform: translateX(-50px);
        }
        to {
          opacity: 1;
          transform: translateX(0);
        }
      }

      @keyframes fadeInRight {
        from {
          opacity: 0;
          transform: translateX(50px);
        }
        to {
          opacity: 1;
          transform: translateX(0);
        }
      }

      @keyframes fadeIn {
        from {
          opacity: 0;
        }
        to {
          opacity: 1;
        }
      }

      /* Responsive Styles */
      @media (max-width: 992px) {
        .dashboard-hero {
          flex-direction: column;
          text-align: center;
          padding: 3rem 5%;
        }

        .hero-content {
          padding-right: 0;
          margin-bottom: 3rem;
        }

        .cta-buttons {
          justify-content: center;
        }

        h1 {
          font-size: 2.8rem;
        }
      }

      @media (max-width: 768px) {
        .dashboard-header {
          flex-direction: column;
          padding: 1.5rem;
        }

        .logo {
          margin-bottom: 1.5rem;
        }

        .nav-links {
          margin-bottom: 1.5rem;
        }

        h1 {
          font-size: 2.2rem;
        }

        .subtitle {
          font-size: 1.1rem;
        }

        .cta-card {
          padding: 2rem;
        }

        .testimonial-card {
          padding: 2rem 1.5rem;
          min-height: 400px;
        }

        .testimonial-content p {
          font-size: 1.1rem;
        }
      }

      @media (max-width: 576px) {
        .auth-buttons {
          width: 100%;
          flex-direction: column;
          gap: 0.5rem;
        }

        .btn {
          width: 100%;
        }

        .cta-buttons {
          flex-direction: column;
          gap: 1rem;
        }

        .feature-card {
          padding: 1.5rem;
        }

        .stats-grid {
          grid-template-columns: 1fr;
        }
      }
    </style>
  </head>
  <body>
    <header class="dashboard-header">
      <div class="logo">
        <i class="fas fa-check-double"></i>
        <span>AccountAble</span>
      </div>
      <nav class="nav-links">
        <a href="#features">Features</a>
        <a href="#testimonials">Testimonials</a>
        <a href="#pricing">Pricing</a>
        <a href="#contact">Contact</a>
      </nav>
      <div class="auth-buttons">
        <a href="login.php" class="btn login-btn">
          <i class="fas fa-sign-in-alt"></i> Log In
        </a>
        <a href="signup.php" class="btn signup-btn">
          <i class="fas fa-user-plus"></i> Sign Up
        </a>
      </div>
    </header>

    <main class="dashboard-hero">
      <div class="hero-content">
        <h1>Transform Your <span>Productivity</span> With Accountability</h1>
        <p class="subtitle">
          Achieve your goals faster with our powerful accountability system.
          Track progress, get support from partners, and stay motivated with
          real results.
        </p>

        <div class="cta-buttons">
          <a href="signup.html" class="btn primary-btn">
            <i class="fas fa-rocket"></i> Get Started - It's Free
          </a>
          <a href="#features" class="btn secondary-btn">
            <i class="fas fa-play-circle"></i> See How It Works
          </a>
        </div>
      </div>
      <div class="hero-image">
        <img
          src="https://illustrations.popsy.co/emerald/digital-nomad.svg"
          alt="Accountability illustration"
        />
      </div>
    </main>

    <section class="features" id="features">
      <h2 class="section-title">Powerful Features</h2>
      <p class="section-subtitle">
        Everything you need to stay accountable and achieve your goals
      </p>
      <div class="features-grid">
        <div class="feature-card">
          <div class="feature-icon">
            <i class="fas fa-bullseye"></i>
          </div>
          <h3>Smart Goal Tracking</h3>
          <p>
            Set SMART goals with our intuitive system and track your progress
            with detailed metrics and milestones.
          </p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">
            <i class="fas fa-users"></i>
          </div>
          <h3>Accountability Partners</h3>
          <p>
            Connect with vetted accountability partners who will challenge and
            support you to reach your targets.
          </p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">
            <i class="fas fa-chart-line"></i>
          </div>
          <h3>Advanced Analytics</h3>
          <p>
            Visualize your progress with beautiful charts and get insights to
            optimize your performance.
          </p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">
            <i class="fas fa-bell"></i>
          </div>
          <h3>Smart Reminders</h3>
          <p>
            Never miss a check-in with customizable reminders and notifications
            tailored to your schedule.
          </p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">
            <i class="fas fa-trophy"></i>
          </div>
          <h3>Rewards System</h3>
          <p>
            Earn badges and rewards for consistency and achievement to keep you
            motivated long-term.
          </p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">
            <i class="fas fa-lock"></i>
          </div>
          <h3>Secure & Private</h3>
          <p>
            Your data is protected with enterprise-grade security and you
            control what you share.
          </p>
        </div>
      </div>
    </section>

    <section class="stats">
      <div class="stats-grid">
        <div class="stat-item">
          <div class="stat-number" id="userCount">10,000+</div>
          <div class="stat-label">Active Users</div>
        </div>
        <div class="stat-item">
          <div class="stat-number" id="goalCount">250,000+</div>
          <div class="stat-label">Goals Achieved</div>
        </div>
        <div class="stat-item">
          <div class="stat-number" id="partnerCount">15,000+</div>
          <div class="stat-label">Accountability Partners</div>
        </div>
        <div class="stat-item">
          <div class="stat-number" id="satisfactionRate">98%</div>
          <div class="stat-label">User Satisfaction</div>
        </div>
      </div>
    </section>

    <section class="testimonials" id="testimonials">
      <h2 class="section-title">Success Stories</h2>
      <p class="section-subtitle">
        Don't just take our word for it - hear from our users
      </p>

      <div class="testimonial-container">
        <div class="testimonial-slider" id="testimonialSlider">
          <div class="testimonial-card">
            <div class="testimonial-content">
              <p>
                AccountAble completely transformed how I approach my business
                goals. In just 6 months, I've doubled my productivity and
                finally launched the side project I'd been putting off for
                years. The accountability partners are game-changers!
              </p>
              <div class="testimonial-author">
                <img
                  src="https://randomuser.me/api/portraits/women/44.jpg"
                  alt="Sarah J."
                />
                <div class="author-info">
                  <h4>Sarah Johnson</h4>
                  <span>Entrepreneur & Founder</span>
                </div>
              </div>
            </div>
          </div>
          <div class="testimonial-card">
            <div class="testimonial-content">
              <p>
                As a freelancer, staying disciplined was always my biggest
                challenge. With AccountAble, I've built consistent work habits
                that helped me increase my income by 40% this year. The progress
                tracking keeps me honest and motivated.
              </p>
              <div class="testimonial-author">
                <img
                  src="https://randomuser.me/api/portraits/men/32.jpg"
                  alt="Michael T."
                />
                <div class="author-info">
                  <h4>Michael Thompson</h4>
                  <span>Freelance Designer</span>
                </div>
              </div>
            </div>
          </div>
          <div class="testimonial-card">
            <div class="testimonial-content">
              <p>
                I've tried every productivity app out there, but none created
                the real accountability I needed. This platform connected me
                with an amazing partner who checks in weekly. Together we've
                both lost over 30lbs and built sustainable fitness habits.
              </p>
              <div class="testimonial-author">
                <img
                  src="https://randomuser.me/api/portraits/women/68.jpg"
                  alt="Priya K."
                />
                <div class="author-info">
                  <h4>Priya Kapoor</h4>
                  <span>Marketing Director</span>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="slider-nav">
          <div class="slider-dot active" data-index="0"></div>
          <div class="slider-dot" data-index="1"></div>
          <div class="slider-dot" data-index="2"></div>
        </div>
      </div>
    </section>

    <section class="cta-section">
      <div class="cta-card">
        <h2>Ready to Transform Your Productivity?</h2>
        <p>
          Join thousands of professionals, entrepreneurs, and goal-getters who
          are achieving more with accountability. Start your free 14-day trial
          today - no credit card required.
        </p>
        <div class="cta-buttons" style="justify-content: center">
          <a
            href="signup.html"
            class="btn primary-btn"
            style="padding: 1rem 2.5rem"
          >
            <i class="fas fa-play"></i> Start Free Trial
          </a>
        </div>
      </div>
    </section>

    <footer class="dashboard-footer" id="contact">
      <div class="footer-content">
        <div class="footer-about">
          <div class="footer-logo">
            <i class="fas fa-check-double"></i>
            <span>AccountAble</span>
          </div>
          <p>
            The most powerful accountability platform designed to help you
            achieve your personal and professional goals through structured
            tracking and community support.
          </p>
          <div class="footer-social">
            <a href="#"><i class="fab fa-twitter"></i></a>
            <a href="#"><i class="fab fa-facebook-f"></i></a>
            <a href="#"><i class="fab fa-instagram"></i></a>
            <a href="#"><i class="fab fa-linkedin-in"></i></a>
          </div>
        </div>
        <div class="footer-links">
          <h3>Quick Links</h3>
          <ul>
            <li><a href="#features">Features</a></li>
            <li><a href="#testimonials">Testimonials</a></li>
            <li><a href="#pricing">Pricing</a></li>
            <li><a href="blog.html">Blog</a></li>
            <li><a href="faq.html">FAQ</a></li>
          </ul>
        </div>
        <div class="footer-links">
          <h3>Company</h3>
          <ul>
            <li><a href="about.html">About Us</a></li>
            <li><a href="careers.html">Careers</a></li>
            <li><a href="press.html">Press</a></li>
            <li><a href="partners.html">Partners</a></li>
            <li><a href="contact.html">Contact</a></li>
          </ul>
        </div>
        <div class="footer-contact">
          <h3>Contact Us</h3>
          <p>
            <i class="fas fa-map-marker-alt"></i>
            123 Accountability St, Suite 100<br />
            San Francisco, CA 94107
          </p>
          <p>
            <i class="fas fa-envelope"></i>
            hello@accountable.com
          </p>
          <p>
            <i class="fas fa-phone-alt"></i>
            +1 (555) 123-4567
          </p>
        </div>
      </div>
      <div class="footer-bottom">
        <p>
          &copy; 2023 AccountAble. All rights reserved. |
          <a href="privacy.html" style="color: var(--accent-color)"
            >Privacy Policy</a
          >
          |
          <a href="terms.html" style="color: var(--accent-color)"
            >Terms of Service</a
          >
        </p>
      </div>
    </footer>

    <script>
      // Testimonial Slider
      const slider = document.getElementById("testimonialSlider");
      const dots = document.querySelectorAll(".slider-dot");
      let currentIndex = 0;
      const testimonialCount =
        document.querySelectorAll(".testimonial-card").length;

      function updateSlider(index) {
        currentIndex = index;
        slider.scrollTo({
          left: slider.offsetWidth * index,
          behavior: "smooth",
        });

        // Update dot indicators
        dots.forEach((dot, i) => {
          if (i === index) {
            dot.classList.add("active");
          } else {
            dot.classList.remove("active");
          }
        });
      }

      // Set up dot navigation
      dots.forEach((dot) => {
        dot.addEventListener("click", () => {
          const index = parseInt(dot.getAttribute("data-index"));
          updateSlider(index);
        });
      });

      // Auto-advance slider
      setInterval(() => {
        currentIndex = (currentIndex + 1) % testimonialCount;
        updateSlider(currentIndex);
      }, 8000);

      // Animate stats counters
      function animateValue(id, start, end, duration) {
        const obj = document.getElementById(id);
        let startTimestamp = null;
        const step = (timestamp) => {
          if (!startTimestamp) startTimestamp = timestamp;
          const progress = Math.min((timestamp - startTimestamp) / duration, 1);
          const value = Math.floor(progress * (end - start) + start);
          obj.innerHTML =
            value.toLocaleString() + (id === "satisfactionRate" ? "%" : "+");
          if (progress < 1) {
            window.requestAnimationFrame(step);
          }
        };
        window.requestAnimationFrame(step);
      }

      // Start animations when stats section comes into view
      const statsSection = document.querySelector(".stats");
      const observer = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              animateValue("userCount", 0, 10000, 2000);
              animateValue("goalCount", 0, 250000, 2000);
              animateValue("partnerCount", 0, 15000, 2000);
              animateValue("satisfactionRate", 0, 98, 2000);
              observer.unobserve(entry.target);
            }
          });
        },
        { threshold: 0.5 }
      );

      observer.observe(statsSection);

      // Smooth scrolling for anchor links
      document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
        anchor.addEventListener("click", function (e) {
          e.preventDefault();
          const targetId = this.getAttribute("href");
          if (targetId === "#") return;

          const targetElement = document.querySelector(targetId);
          if (targetElement) {
            window.scrollTo({
              top: targetElement.offsetTop - 80,
              behavior: "smooth",
            });
          }
        });
      });
    </script>
  </body>
</html>
