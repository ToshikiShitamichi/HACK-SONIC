<style>
.quiz-top-container {
    text-align: center;
    background: var(--white);
    padding: 32px 28px;
    border-radius: 20px;
    border: 1.5px solid var(--border);
    box-shadow: 0 4px 16px rgba(232, 64, 92, 0.06);
}

.quiz-top-container h1 {
    font-size: 18px;
    font-weight: 800;
    margin-bottom: 20px;
    color: var(--text);
}

.quiz-top-container h1::before {
    content: '';
    display: inline-block;
    width: 8px;
    height: 8px;
    background: var(--pink);
    border-radius: 50%;
    margin-right: 10px;
    vertical-align: middle;
}

.quiz-btn-group {
    display: flex;
    gap: 12px;
}

.quiz-top-btn {
    flex: 1;
    display: block;
    padding: 14px 20px;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.5px;
    color: var(--white);
    background: linear-gradient(135deg, var(--pink-lt) 0%, var(--pink) 55%, var(--pink-deep) 100%);
    border: none;
    border-radius: 50px;
    cursor: pointer;
    transition: opacity 0.2s ease, transform 0.12s ease, box-shadow 0.2s ease;
    text-decoration: none;
    box-shadow: 0 4px 16px rgba(232, 64, 92, 0.25);
}

.quiz-top-btn:hover {
    opacity: 0.92;
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(232, 64, 92, 0.35);
}

.quiz-top-btn.secondary {
    background: transparent;
    color: var(--pink);
    border: 1.5px solid var(--border);
    box-shadow: none;
}

.quiz-top-btn.secondary:hover {
    background: var(--pink-pale);
    border-color: var(--pink-lt);
    box-shadow: 0 4px 16px rgba(232, 64, 92, 0.12);
}

@media (max-width: 500px) {
    .quiz-btn-group {
        flex-direction: column;
    }
}
</style>

<div class="quiz-top-container">
    <h1>旅クイズ</h1>

    <div class="quiz-btn-group">
        <a href="quiz/quiz-start.html" class="quiz-top-btn">
            クイズを始める
        </a>

        <a href="quiz/interest.html" class="quiz-top-btn secondary">
            気になる一覧
        </a>
    </div>
</div>
