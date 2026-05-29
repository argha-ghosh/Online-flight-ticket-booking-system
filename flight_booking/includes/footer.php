<style>
    footer {
        background: linear-gradient(135deg, #0b72e6, #0556b3);
        color: white;
        padding: 32px 24px;
        text-align: center;
    }

    .footer-container {
        max-width: 1200px;
        margin: 0 auto;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 18px;
    }

    .footer-container p {
        margin: 6px 0;
        line-height: 1.6;
        color: #e5f2ff;
    }

    .footer-container a {
        color: #7dd3fc;
        text-decoration: none;
        transition: color 0.25s ease;
    }

    .footer-container a:hover {
        color: #ffffff;
    }

    .social-icons {
        display: flex;
        gap: 12px;
        justify-content: center;
        align-items: center;
    }

    .social-icons a {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.16);
        color: white;
        transition: all 0.25s ease;
    }

    .social-icons a:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: translateY(-2px);
    }

    .contact-info {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 16px;
        color: #dbeafe;
        font-size: 0.9rem;
    }

    @media (max-width: 768px) {
        .footer-container {
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
    }
</style>
<footer>
    <div class="footer-container">
        <p>&copy; <?php echo date("Y"); ?> GoZayan. All rights reserved.</p>
        <p>
            <a href="#">Privacy Policy</a> |
            <a href="#">Terms &amp; Conditions</a>
        </p>
        <div class="social-icons">
            <a href="https://www.aiub.edu/"><i class="fab fa-facebook-f"></i></a>
            <a href="https://x.com/aiub_edu?lang=en"><i class="fab fa-twitter"></i></a>
            <a href="https://www.linkedin.com/school/aiubedu/"><i class="fab fa-linkedin"></i></a>
        </div>
        <div class="contact-info">
            <span>Email: support@gozayan.com</span>
            <span>Phone: +880 1721104949</span>
            <span>Address: Dhaka, Bangladesh</span>
        </div>
    </div>
</footer>
